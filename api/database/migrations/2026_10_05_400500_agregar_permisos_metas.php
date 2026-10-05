<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo de metas. Solo admin recibe los permisos: el asesor no gana ninguno nuevo; cuando la empresa activa
 * `meta_consolidada_visible_asesor`, el middleware `acceso_metas` le abre la meta de empresa sin el detalle de otros asesores.
 */
return new class extends Migration
{
    private const PERMISOS = ['listar', 'configurar', 'recalcular'];

    private const ROLES = ['admin'];

    public function up(): void
    {
        foreach (self::PERMISOS as $accion) {
            DB::table('permisos')->updateOrInsert(
                ['modulo' => 'metas', 'accion' => $accion],
                ['creado_en' => now(), 'ultima_actualizacion' => now()],
            );
        }

        $permisos = DB::table('permisos')->where('modulo', 'metas')->pluck('id');

        foreach (DB::table('roles')->whereIn('nombre', self::ROLES)->pluck('id') as $rolId) {
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

    public function down(): void
    {
        $permisoIds = DB::table('permisos')->where('modulo', 'metas')->pluck('id');

        DB::table('rol_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('usuario_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('permisos')->whereIn('id', $permisoIds)->delete();
    }
};
