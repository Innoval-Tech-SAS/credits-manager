<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `PermisoSeeder`/`EmpresaConstants::PERMISOS_POR_ROL` solo aplican al crear una
 * empresa nueva — las empresas ya existentes no reciben retroactivamente los
 * permisos nuevos del catálogo. Esta migración inserta los 3 permisos nuevos de
 * `creditos` (aprobar/desembolsar/cancelar) y se los asigna al rol `admin` de
 * cada empresa ya existente, igual que reciben automáticamente el resto del
 * catálogo al crearse.
 */
return new class extends Migration
{
    public function up(): void
    {
        $acciones = ['aprobar', 'desembolsar', 'cancelar'];

        $idsNuevos = collect($acciones)->map(function (string $accion) {
            DB::table('permisos')->updateOrInsert(
                ['modulo' => 'creditos', 'accion' => $accion],
                ['creado_en' => now(), 'ultima_actualizacion' => now()],
            );

            return DB::table('permisos')->where(['modulo' => 'creditos', 'accion' => $accion])->value('id');
        });

        foreach (DB::table('roles')->where('nombre', 'admin')->pluck('id') as $rolId) {
            foreach ($idsNuevos as $permisoId) {
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
            ->where('modulo', 'creditos')
            ->whereIn('accion', ['aprobar', 'desembolsar', 'cancelar'])
            ->pluck('id');

        DB::table('rol_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('permisos')->whereIn('id', $permisoIds)->delete();
    }
};
