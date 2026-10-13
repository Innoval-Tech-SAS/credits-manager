<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Catálogo de solo lectura/edición de flags: igual que configuracion_contable, lo administran admin y contable. */
return new class extends Migration
{
    private const PERMISOS = ['listar_tipos_documento_negocio', 'editar_tipos_documento_negocio'];

    private const ROLES = ['admin', 'contable'];

    public function up(): void
    {
        foreach (self::PERMISOS as $accion) {
            DB::table('permisos')->updateOrInsert(
                ['modulo' => 'configuracion_contable', 'accion' => $accion],
                ['creado_en' => now(), 'ultima_actualizacion' => now()],
            );
        }

        $permisos = DB::table('permisos')->where('modulo', 'configuracion_contable')->whereIn('accion', self::PERMISOS)->pluck('id');

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
        $permisoIds = DB::table('permisos')->where('modulo', 'configuracion_contable')->whereIn('accion', self::PERMISOS)->pluck('id');

        DB::table('rol_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('usuario_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('permisos')->whereIn('id', $permisoIds)->delete();
    }
};
