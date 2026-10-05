<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Separa `contabilidad` y los módulos tributarios de `gastos` en módulos con permiso propio: `terceros`,
 * `configuracion_contable`, `informes_contables` y `tributario`. Cada permiso vigente se traduce a los nuevos
 * que dan la misma acción, en roles y en excepciones por usuario (con el mismo `concedido`), así que nadie gana
 * ni pierde acceso al desplegar. Los permisos viejos se conservan: la migración solo agrega, y `down()` quita lo
 * que agregó. Los roles de asesor no tienen ninguno de los permisos de origen, así que no reciben nada nuevo.
 */
return new class extends Migration
{
    /** 'modulo.accion' vigente hasta ahora => 'modulo.accion' nuevos que dan exactamente lo mismo. */
    private const TRADUCCIONES = [
        'contabilidad.listar' => ['contabilidad.listar', 'configuracion_contable.listar', 'informes_contables.listar', 'terceros.listar'],
        'contabilidad.crear' => ['configuracion_contable.crear', 'terceros.crear'],
        'contabilidad.editar' => ['configuracion_contable.editar', 'terceros.editar'],
        'contabilidad.configurar' => ['configuracion_contable.configurar'],
        'contabilidad.cerrar_periodo' => ['configuracion_contable.cerrar_periodo'],
        'contabilidad.reabrir_periodo' => ['configuracion_contable.reabrir_periodo'],
        'contabilidad.exportar' => ['informes_contables.exportar'],
        'gastos.listar' => ['tributario.listar'],
        'gastos.parametrizar' => ['tributario.parametrizar'],
    ];

    /** Módulos que no existían antes de esta migración: todo lo que tengan en `permisos` lo creó ella. */
    private const MODULOS_NUEVOS = ['terceros', 'configuracion_contable', 'informes_contables', 'tributario'];

    public function up(): void
    {
        $destinos = collect(self::TRADUCCIONES)->flatten()->unique()->values();

        foreach ($destinos as $clave) {
            if (in_array(explode('.', $clave)[0], self::MODULOS_NUEVOS, true)) {
                $this->asegurarPermiso($clave);
            }
        }

        foreach (self::TRADUCCIONES as $origen => $nuevos) {
            $origenId = $this->idDe($origen);

            if ($origenId === null) {
                continue;
            }

            foreach (DB::table('rol_permiso')->where('permiso_id', $origenId)->pluck('rol_id') as $rolId) {
                foreach ($nuevos as $nuevo) {
                    $this->asignarARol($rolId, $this->idDe($nuevo));
                }
            }

            foreach (DB::table('usuario_permiso')->where('permiso_id', $origenId)->get(['usuario_id', 'concedido']) as $excepcion) {
                foreach ($nuevos as $nuevo) {
                    $this->asignarAUsuario($excepcion->usuario_id, $this->idDe($nuevo), (bool) $excepcion->concedido);
                }
            }
        }

        foreach (DB::table('roles')->where('nombre', 'admin')->pluck('id') as $rolId) {
            foreach ($destinos as $clave) {
                $this->asignarARol($rolId, $this->idDe($clave));
            }
        }
    }

    public function down(): void
    {
        $permisoIds = DB::table('permisos')->whereIn('modulo', self::MODULOS_NUEVOS)->pluck('id');

        DB::table('rol_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('usuario_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('permisos')->whereIn('id', $permisoIds)->delete();
    }

    private function asegurarPermiso(string $clave): void
    {
        [$modulo, $accion] = explode('.', $clave);

        DB::table('permisos')->insertOrIgnore([
            'modulo' => $modulo,
            'accion' => $accion,
            'creado_en' => now(),
            'ultima_actualizacion' => now(),
        ]);
    }

    private function asignarARol(int $rolId, ?int $permisoId): void
    {
        if ($permisoId === null) {
            return;
        }

        DB::table('rol_permiso')->insertOrIgnore([
            'rol_id' => $rolId,
            'permiso_id' => $permisoId,
            'creado_en' => now(),
            'ultima_actualizacion' => now(),
        ]);
    }

    private function asignarAUsuario(int $usuarioId, ?int $permisoId, bool $concedido): void
    {
        if ($permisoId === null) {
            return;
        }

        DB::table('usuario_permiso')->insertOrIgnore([
            'usuario_id' => $usuarioId,
            'permiso_id' => $permisoId,
            'concedido' => $concedido,
            'creado_en' => now(),
            'ultima_actualizacion' => now(),
        ]);
    }

    private function idDe(string $clave): ?int
    {
        [$modulo, $accion] = explode('.', $clave);

        return DB::table('permisos')->where(['modulo' => $modulo, 'accion' => $accion])->value('id');
    }
};
