<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Cada hoja de importación pasa a tener su permiso (`importaciones.importar_<hoja>`). Antes hacían falta dos:
 * `importaciones.ejecutar` (o `listar`, para ver) y el de crear del módulo destino. Recibe `importar_<hoja>` quien hoy
 * tiene efectivamente ambos (rol más excepciones por usuario, con su `concedido`): se asigna al rol que tiene los dos, y
 * por usuario solo cuando su resultado difiere del de su rol, así que nadie gana ni pierde acceso al desplegar. El
 * admin recibe todos. `ejecutar` y `listar` quedan sin uso y se retiran con borrado lógico, como en
 * `retirar_permisos_genericos_sin_uso`; `down()` los restaura y quita lo agregado.
 */
return new class extends Migration
{
    /** Permiso por hoja => permiso de crear del módulo destino que se exigía junto con `importaciones.ejecutar`. */
    private const DESTINOS = [
        'importaciones.importar_clientes' => 'clientes.crear',
        'importaciones.importar_creditos' => 'creditos.crear',
        'importaciones.importar_plan_cuotas' => 'creditos.crear',
        'importaciones.importar_pagos' => 'pagos.crear',
        'importaciones.importar_saldos_iniciales' => 'configuracion_contable.crear_plan_cuentas',
        'importaciones.importar_plan_cuentas' => 'configuracion_contable.crear_plan_cuentas',
        'importaciones.importar_terceros' => 'terceros.crear',
        'importaciones.importar_gastos' => 'gastos.crear',
        'importaciones.importar_actividades_economicas' => 'tributario.crear_actividades_economicas',
        'importaciones.importar_valores_uvt' => 'tributario.crear_valores_uvt',
        'importaciones.importar_tarifas_reteica' => 'tributario.crear_tarifas_reteica',
    ];

    private const EJECUTAR = 'importaciones.ejecutar';

    private const RETIRADOS = ['importaciones.listar', 'importaciones.ejecutar'];

    public function up(): void
    {
        foreach (array_keys(self::DESTINOS) as $clave) {
            [$modulo, $accion] = explode('.', $clave);

            DB::table('permisos')->insertOrIgnore([
                'modulo' => $modulo,
                'accion' => $accion,
                'creado_en' => now(),
                'ultima_actualizacion' => now(),
            ]);
        }

        $ejecutarId = $this->idDe(self::EJECUTAR);
        $porRol = DB::table('rol_permiso')->get(['rol_id', 'permiso_id'])
            ->groupBy('rol_id')
            ->map(fn ($filas) => $filas->pluck('permiso_id')->map(fn ($id) => (int) $id)->all());
        $excepciones = DB::table('usuario_permiso')->get(['usuario_id', 'permiso_id', 'concedido'])
            ->groupBy('usuario_id')
            ->map(fn ($filas) => $filas->mapWithKeys(fn ($fila) => [(int) $fila->permiso_id => (bool) $fila->concedido])->all());
        $roles = DB::table('roles')->get(['id', 'nombre']);
        $rolesAdmin = $roles->where('nombre', 'admin')->pluck('id')->all();
        $usuarios = DB::table('usuarios')->whereNotNull('rol_id')->whereNotIn('rol_id', $rolesAdmin)->get(['id', 'rol_id']);

        foreach (self::DESTINOS as $nuevo => $destino) {
            $nuevoId = $this->idDe($nuevo);
            $destinoId = $this->idDe($destino);

            if ($nuevoId === null || $ejecutarId === null || $destinoId === null) {
                continue;
            }

            foreach ($roles as $rol) {
                $permisosDelRol = $porRol->get($rol->id, []);

                if ($rol->nombre === 'admin' || (in_array($ejecutarId, $permisosDelRol, true) && in_array($destinoId, $permisosDelRol, true))) {
                    $this->asignarARol((int) $rol->id, $nuevoId);
                }
            }

            foreach ($usuarios as $usuario) {
                $permisosDelRol = $porRol->get($usuario->rol_id, []);
                $propias = $excepciones->get($usuario->id, []);
                $efectivo = fn (int $permisoId) => $propias[$permisoId] ?? in_array($permisoId, $permisosDelRol, true);

                $loTeniaPorElRol = in_array($ejecutarId, $permisosDelRol, true) && in_array($destinoId, $permisosDelRol, true);
                $loTenia = $efectivo($ejecutarId) && $efectivo($destinoId);

                if ($loTenia !== $loTeniaPorElRol) {
                    $this->asignarAUsuario((int) $usuario->id, $nuevoId, $loTenia);
                }
            }
        }

        $this->retirados()->whereNull('borrado_en')->update(['borrado_en' => now(), 'ultima_actualizacion' => now()]);
    }

    public function down(): void
    {
        $this->retirados()->whereNotNull('borrado_en')->update(['borrado_en' => null, 'ultima_actualizacion' => now()]);

        $permisoIds = collect(array_keys(self::DESTINOS))
            ->map(fn (string $clave) => $this->idDe($clave))
            ->filter()
            ->values();

        DB::table('rol_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('usuario_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('permisos')->whereIn('id', $permisoIds)->delete();
    }

    private function asignarARol(int $rolId, int $permisoId): void
    {
        DB::table('rol_permiso')->insertOrIgnore([
            'rol_id' => $rolId,
            'permiso_id' => $permisoId,
            'creado_en' => now(),
            'ultima_actualizacion' => now(),
        ]);
    }

    private function asignarAUsuario(int $usuarioId, int $permisoId, bool $concedido): void
    {
        DB::table('usuario_permiso')->insertOrIgnore([
            'usuario_id' => $usuarioId,
            'permiso_id' => $permisoId,
            'concedido' => $concedido,
            'creado_en' => now(),
            'ultima_actualizacion' => now(),
        ]);
    }

    private function retirados(): Builder
    {
        return DB::table('permisos')->where(function (Builder $consulta) {
            foreach (self::RETIRADOS as $clave) {
                [$modulo, $accion] = explode('.', $clave);
                $consulta->orWhere(fn (Builder $condicion) => $condicion->where('modulo', $modulo)->where('accion', $accion));
            }
        });
    }

    private function idDe(string $clave): ?int
    {
        [$modulo, $accion] = explode('.', $clave);
        $id = DB::table('permisos')->where(['modulo' => $modulo, 'accion' => $accion])->value('id');

        return $id === null ? null : (int) $id;
    }
};
