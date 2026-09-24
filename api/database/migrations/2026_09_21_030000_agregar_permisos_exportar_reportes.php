<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mismo patrón que `agregar_permisos_auditoria`: el catálogo y PERMISOS_POR_ROL solo aplican al crear
 * una empresa. Inserta `cartera.exportar`, `contabilidad.exportar` y `creditos.exportar` y los asigna a
 * los roles ya existentes: `admin` recibe los tres; `contable` y `auditoria`, los de cartera y contabilidad.
 */
return new class extends Migration
{
    private const ROLES_POR_PERMISO = [
        'cartera' => ['admin', 'contable', 'auditoria'],
        'contabilidad' => ['admin', 'contable', 'auditoria'],
        'creditos' => ['admin'],
    ];

    public function up(): void
    {
        foreach (self::ROLES_POR_PERMISO as $modulo => $roles) {
            DB::table('permisos')->updateOrInsert(
                ['modulo' => $modulo, 'accion' => 'exportar'],
                ['creado_en' => now(), 'ultima_actualizacion' => now()],
            );

            $permisoId = DB::table('permisos')->where(['modulo' => $modulo, 'accion' => 'exportar'])->value('id');

            foreach (DB::table('roles')->whereIn('nombre', $roles)->pluck('id') as $rolId) {
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
        $permisoIds = DB::table('permisos')
            ->where('accion', 'exportar')
            ->whereIn('modulo', array_keys(self::ROLES_POR_PERMISO))
            ->pluck('id');

        DB::table('rol_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('usuario_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('permisos')->whereIn('id', $permisoIds)->delete();
    }
};
