<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permisos del módulo de facturación. El catálogo y PERMISOS_POR_ROL solo aplican al crear una empresa, así que
 * se conceden también a los roles que ya existen. Admin recibe todo el catálogo; contable recibe el flujo completo
 * de facturación. No se tocan excepciones por usuario.
 */
return new class extends Migration
{
    private const PERMISOS = ['listar', 'crear', 'anular', 'imprimir', 'configurar'];

    private const ROLES = ['admin', 'contable'];

    public function up(): void
    {
        foreach (self::PERMISOS as $accion) {
            DB::table('permisos')->updateOrInsert(
                ['modulo' => 'facturacion', 'accion' => $accion],
                ['creado_en' => now(), 'ultima_actualizacion' => now()],
            );
        }

        $permisos = DB::table('permisos')->where('modulo', 'facturacion')->pluck('id');

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
        $permisoIds = DB::table('permisos')->where('modulo', 'facturacion')->pluck('id');

        DB::table('rol_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('usuario_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('permisos')->whereIn('id', $permisoIds)->delete();
    }
};
