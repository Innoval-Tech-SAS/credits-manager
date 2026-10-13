<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Calendario tributario: solo admin y contable lo usan; el asesor no gana ningún permiso nuevo. */
return new class extends Migration
{
    private const PERMISOS = ['listar', 'configurar', 'crear', 'editar', 'cambiar_estado'];

    private const ROLES = ['admin', 'contable'];

    public function up(): void
    {
        foreach (self::PERMISOS as $accion) {
            DB::table('permisos')->updateOrInsert(
                ['modulo' => 'obligaciones_tributarias', 'accion' => $accion],
                ['creado_en' => now(), 'ultima_actualizacion' => now()],
            );
        }

        $permisos = DB::table('permisos')->where('modulo', 'obligaciones_tributarias')->pluck('id');

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
        $permisoIds = DB::table('permisos')->where('modulo', 'obligaciones_tributarias')->pluck('id');

        DB::table('rol_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('usuario_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('permisos')->whereIn('id', $permisoIds)->delete();
    }
};
