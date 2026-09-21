<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mismo patrón que `agregar_permiso_eliminar_credito`: el catálogo y PERMISOS_POR_ROL solo
 * aplican al crear una empresa. Inserta `creditos.editar` y se lo asigna únicamente al rol
 * `admin` de cada empresa ya existente; los demás roles lo reciben por permiso individual.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('permisos')->updateOrInsert(
            ['modulo' => 'creditos', 'accion' => 'editar'],
            ['creado_en' => now(), 'ultima_actualizacion' => now()],
        );

        $permisoId = DB::table('permisos')->where(['modulo' => 'creditos', 'accion' => 'editar'])->value('id');

        foreach (DB::table('roles')->where('nombre', 'admin')->pluck('id') as $rolId) {
            DB::table('rol_permiso')->insertOrIgnore([
                'rol_id' => $rolId,
                'permiso_id' => $permisoId,
                'creado_en' => now(),
                'ultima_actualizacion' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $permisoId = DB::table('permisos')->where(['modulo' => 'creditos', 'accion' => 'editar'])->value('id');

        DB::table('rol_permiso')->where('permiso_id', $permisoId)->delete();
        DB::table('permisos')->where('id', $permisoId)->delete();
    }
};
