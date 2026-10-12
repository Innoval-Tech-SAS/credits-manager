<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Cada exportación pasa a tener su permiso dentro de su módulo (`clientes.exportar_listado`,
 * `informes_contables.exportar_libro_mayor`...), declarado en `ExportacionConstants::PERMISOS_POR_REPORTE`. Mismo patrón
 * que `separar_permisos_por_pantalla`: cada `<modulo>.exportar` se traduce a los permisos por reporte de su módulo, en
 * roles y en excepciones por usuario (mismo `concedido`), así que nadie gana ni pierde acceso al desplegar; el admin
 * recibe todos. Los genéricos quedan sin uso y se retiran con borrado lógico, como en `retirar_permisos_genericos_sin_uso`.
 * `down()` los restaura y quita lo agregado.
 */
return new class extends Migration
{
    /** 'modulo.exportar' genérico => permisos por reporte que dan exactamente lo mismo. */
    private const TRADUCCIONES = [
        'clientes.exportar' => ['clientes.exportar_listado'],
        'creditos.exportar' => ['creditos.exportar_listado', 'creditos.exportar_estado_cuenta'],
        'cartera.exportar' => ['cartera.exportar_listado', 'cartera.exportar_reporte_empleador'],
        'pagos.exportar' => ['pagos.exportar_listado'],
        'gastos.exportar' => ['gastos.exportar_gastos', 'gastos.exportar_gastos_centro_costo'],
        'informes_contables.exportar' => [
            'informes_contables.exportar_libro_diario', 'informes_contables.exportar_libro_mayor',
            'informes_contables.exportar_caja_bancos', 'informes_contables.exportar_balance_prueba',
            'informes_contables.exportar_estados_financieros', 'informes_contables.exportar_auxiliares',
        ],
        'auditoria.exportar' => ['auditoria.exportar_bitacora'],
    ];

    public function up(): void
    {
        $nuevos = collect(self::TRADUCCIONES)->flatten()->unique()->values();

        foreach ($nuevos as $clave) {
            [$modulo, $accion] = explode('.', $clave);

            DB::table('permisos')->insertOrIgnore([
                'modulo' => $modulo,
                'accion' => $accion,
                'creado_en' => now(),
                'ultima_actualizacion' => now(),
            ]);
        }

        foreach (self::TRADUCCIONES as $origen => $destinos) {
            $origenId = $this->idDe($origen);

            if ($origenId === null) {
                continue;
            }

            foreach (DB::table('rol_permiso')->where('permiso_id', $origenId)->pluck('rol_id') as $rolId) {
                foreach ($destinos as $destino) {
                    $this->asignarARol((int) $rolId, $this->idDe($destino));
                }
            }

            foreach (DB::table('usuario_permiso')->where('permiso_id', $origenId)->get(['usuario_id', 'concedido']) as $excepcion) {
                foreach ($destinos as $destino) {
                    $this->asignarAUsuario((int) $excepcion->usuario_id, $this->idDe($destino), (bool) $excepcion->concedido);
                }
            }
        }

        foreach (DB::table('roles')->where('nombre', 'admin')->pluck('id') as $rolId) {
            foreach ($nuevos as $clave) {
                $this->asignarARol((int) $rolId, $this->idDe($clave));
            }
        }

        $this->genericos()->whereNull('borrado_en')->update(['borrado_en' => now(), 'ultima_actualizacion' => now()]);
    }

    public function down(): void
    {
        $this->genericos()->whereNotNull('borrado_en')->update(['borrado_en' => null, 'ultima_actualizacion' => now()]);

        $permisoIds = collect(self::TRADUCCIONES)->flatten()->unique()
            ->map(fn (string $clave) => $this->idDe($clave))
            ->filter()
            ->values();

        DB::table('rol_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('usuario_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('permisos')->whereIn('id', $permisoIds)->delete();
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

    private function genericos(): Builder
    {
        return DB::table('permisos')->where(function (Builder $consulta) {
            foreach (array_keys(self::TRADUCCIONES) as $clave) {
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
