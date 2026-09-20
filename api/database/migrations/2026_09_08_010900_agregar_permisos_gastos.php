<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mismo patrón que `2026_09_06_040100_agregar_permiso_eliminar_credito.php`:
 * `PermisoSeeder`/`EmpresaConstants::PERMISOS_POR_ROL` solo aplican al crear una
 * empresa nueva — las empresas ya existentes no reciben retroactivamente los
 * permisos nuevos del catálogo. Inserta los permisos del módulo `gastos` y se
 * los asigna al rol `admin` de cada empresa ya existente.
 */
return new class extends Migration
{
    private const ACCIONES = ['listar', 'crear', 'anular', 'parametrizar'];

    public function up(): void
    {
        $rolesAdminIds = DB::table('roles')->where('nombre', 'admin')->pluck('id');

        foreach (self::ACCIONES as $accion) {
            DB::table('permisos')->updateOrInsert(
                ['modulo' => 'gastos', 'accion' => $accion],
                ['creado_en' => now(), 'ultima_actualizacion' => now()],
            );

            $permisoId = DB::table('permisos')->where(['modulo' => 'gastos', 'accion' => $accion])->value('id');

            foreach ($rolesAdminIds as $rolId) {
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
        $permisoIds = DB::table('permisos')->where('modulo', 'gastos')->pluck('id');

        DB::table('rol_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('permisos')->whereIn('id', $permisoIds)->delete();
    }
};
