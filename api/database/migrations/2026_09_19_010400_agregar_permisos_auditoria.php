<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mismo patrón que `agregar_permiso_eliminar_credito`: el catálogo y PERMISOS_POR_ROL solo
 * aplican al crear una empresa. Aquí se insertan `auditoria.listar` y `auditoria.exportar`
 * y se asignan a los roles `auditoria` y `admin` de cada empresa ya existente.
 */
return new class extends Migration
{
    private const ACCIONES = ['listar', 'exportar'];

    private const ROLES = ['admin', 'auditoria'];

    public function up(): void
    {
        foreach (self::ACCIONES as $accion) {
            DB::table('permisos')->updateOrInsert(
                ['modulo' => 'auditoria', 'accion' => $accion],
                ['creado_en' => now(), 'ultima_actualizacion' => now()],
            );

            $permisoId = DB::table('permisos')->where(['modulo' => 'auditoria', 'accion' => $accion])->value('id');

            foreach (DB::table('roles')->whereIn('nombre', self::ROLES)->pluck('id') as $rolId) {
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
        $permisoIds = DB::table('permisos')->where('modulo', 'auditoria')->pluck('id');

        DB::table('rol_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('permisos')->whereIn('id', $permisoIds)->delete();
    }
};
