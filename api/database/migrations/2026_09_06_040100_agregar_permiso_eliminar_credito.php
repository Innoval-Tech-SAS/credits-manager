<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mismo patrón que `2026_09_06_020100_agregar_permisos_de_transicion_de_credito.php`:
 * `PermisoSeeder`/`EmpresaConstants::PERMISOS_POR_ROL` solo aplican al crear una
 * empresa nueva — las empresas ya existentes no reciben retroactivamente los
 * permisos nuevos del catálogo. Inserta `creditos.eliminar` y se lo asigna al rol
 * `admin` de cada empresa ya existente.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('permisos')->updateOrInsert(
            ['modulo' => 'creditos', 'accion' => 'eliminar'],
            ['creado_en' => now(), 'ultima_actualizacion' => now()],
        );

        $permisoId = DB::table('permisos')->where(['modulo' => 'creditos', 'accion' => 'eliminar'])->value('id');

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
        $permisoId = DB::table('permisos')->where(['modulo' => 'creditos', 'accion' => 'eliminar'])->value('id');

        DB::table('rol_permiso')->where('permiso_id', $permisoId)->delete();
        DB::table('permisos')->where('id', $permisoId)->delete();
    }
};
