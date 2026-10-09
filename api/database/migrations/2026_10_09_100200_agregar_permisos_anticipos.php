<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Permisos del módulo Anticipos. Mismo patrón que `2026_10_08_100400_agregar_permisos_egresos.php`. */
return new class extends Migration
{
    private const PERMISOS = ['listar', 'crear'];

    private const ROLES = ['admin', 'contable'];

    public function up(): void
    {
        foreach (self::PERMISOS as $accion) {
            DB::table('permisos')->updateOrInsert(
                ['modulo' => 'anticipos', 'accion' => $accion],
                ['creado_en' => now(), 'ultima_actualizacion' => now()],
            );
        }

        $permisos = DB::table('permisos')->where('modulo', 'anticipos')->pluck('id');

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
        $permisoIds = DB::table('permisos')->where('modulo', 'anticipos')->pluck('id');

        DB::table('rol_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('usuario_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('permisos')->whereIn('id', $permisoIds)->delete();
    }
};
