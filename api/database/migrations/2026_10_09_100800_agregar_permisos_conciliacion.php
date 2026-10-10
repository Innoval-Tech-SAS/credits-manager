<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permisos del módulo Conciliación bancaria. Mismo patrón que `2026_10_08_100400_agregar_permisos_egresos.php`:
 * el catálogo y `PERMISOS_POR_ROL` solo aplican al crear una empresa, así que se conceden también a los roles
 * que ya existen. Admin recibe todo el catálogo. Contable recibe todo excepto `reabrir` ("cierre por el
 * contador; reapertura solo administrador"). Auditoría y consulta solo `listar`, igual que ya tienen con
 * `contabilidad`/`informes_contables`.
 */
return new class extends Migration
{
    private const PERMISOS = ['listar', 'configurar', 'cargar_extracto', 'conciliar', 'cerrar', 'reabrir', 'exportar'];

    private const PERMISOS_POR_ROL = [
        'admin' => ['listar', 'configurar', 'cargar_extracto', 'conciliar', 'cerrar', 'reabrir', 'exportar'],
        'contable' => ['listar', 'configurar', 'cargar_extracto', 'conciliar', 'cerrar', 'exportar'],
        'auditoria' => ['listar'],
        'consulta' => ['listar'],
    ];

    public function up(): void
    {
        foreach (self::PERMISOS as $accion) {
            DB::table('permisos')->updateOrInsert(
                ['modulo' => 'conciliacion', 'accion' => $accion],
                ['creado_en' => now(), 'ultima_actualizacion' => now()],
            );
        }

        $permisoIds = DB::table('permisos')->where('modulo', 'conciliacion')->pluck('id', 'accion');

        foreach (self::PERMISOS_POR_ROL as $nombreRol => $acciones) {
            foreach (DB::table('roles')->where('nombre', $nombreRol)->pluck('id') as $rolId) {
                foreach ($acciones as $accion) {
                    DB::table('rol_permiso')->insertOrIgnore([
                        'rol_id' => $rolId,
                        'permiso_id' => $permisoIds[$accion],
                        'creado_en' => now(),
                        'ultima_actualizacion' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $permisoIds = DB::table('permisos')->where('modulo', 'conciliacion')->pluck('id');

        DB::table('rol_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('usuario_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('permisos')->whereIn('id', $permisoIds)->delete();
    }
};
