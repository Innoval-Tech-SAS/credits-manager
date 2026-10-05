<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo del módulo de importaciones. Admin recibe ambos permisos; ningún otro rol cambia. Cada tipo de carga
 * exige además el permiso de crear de su módulo destino, que ya existe en el catálogo.
 */
return new class extends Migration
{
    private const PERMISOS = ['listar', 'ejecutar'];

    private const ROLES = ['admin'];

    public function up(): void
    {
        foreach (self::PERMISOS as $accion) {
            DB::table('permisos')->updateOrInsert(
                ['modulo' => 'importaciones', 'accion' => $accion],
                ['creado_en' => now(), 'ultima_actualizacion' => now()],
            );
        }

        $permisos = DB::table('permisos')->where('modulo', 'importaciones')->pluck('id');

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
        $permisoIds = DB::table('permisos')->where('modulo', 'importaciones')->pluck('id');

        DB::table('rol_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('usuario_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('permisos')->whereIn('id', $permisoIds)->delete();
    }
};
