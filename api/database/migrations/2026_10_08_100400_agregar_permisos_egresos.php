<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permisos del módulo Egresos (pago de documentos por pagar). Mismo patrón que
 * `2026_10_05_100300_agregar_permisos_facturacion.php`: el catálogo y PERMISOS_POR_ROL solo aplican al crear una
 * empresa, así que se conceden también a los roles que ya existen. Admin recibe todo el catálogo; contable recibe
 * el flujo completo, igual que ya tiene con `gastos.*`.
 */
return new class extends Migration
{
    private const PERMISOS = ['listar', 'crear'];

    private const ROLES = ['admin', 'contable'];

    public function up(): void
    {
        foreach (self::PERMISOS as $accion) {
            DB::table('permisos')->updateOrInsert(
                ['modulo' => 'egresos', 'accion' => $accion],
                ['creado_en' => now(), 'ultima_actualizacion' => now()],
            );
        }

        $permisos = DB::table('permisos')->where('modulo', 'egresos')->pluck('id');

        foreach (self::ROLES as $nombreRol) {
            foreach (DB::table('roles')->where('nombre', $nombreRol)->pluck('id') as $rolId) {
                foreach ($permisos as $permisoId) {
                    DB::table('rol_permiso')->insertOrIgnore([
                        'rol_id' => $rolId,
                        'permiso_id' => $permisoId,
                        'creado_en' => now(),
                        'ultima_actualizacion' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $permisoIds = DB::table('permisos')->where('modulo', 'egresos')->pluck('id');

        DB::table('rol_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('usuario_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('permisos')->whereIn('id', $permisoIds)->delete();
    }
};
