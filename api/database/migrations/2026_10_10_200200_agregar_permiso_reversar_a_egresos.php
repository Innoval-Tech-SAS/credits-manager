<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Permiso `egresos.reversar`. Mismo patrón que `2026_10_08_100400_agregar_permisos_egresos.php`. */
return new class extends Migration
{
    private const ACCION = 'reversar';

    private const ROLES = ['admin', 'contable'];

    public function up(): void
    {
        DB::table('permisos')->updateOrInsert(
            ['modulo' => 'egresos', 'accion' => self::ACCION],
            ['creado_en' => now(), 'ultima_actualizacion' => now()],
        );

        $permisoId = DB::table('permisos')->where('modulo', 'egresos')->where('accion', self::ACCION)->value('id');

        foreach (self::ROLES as $nombreRol) {
            foreach (DB::table('roles')->where('nombre', $nombreRol)->pluck('id') as $rolId) {
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
        $permisoId = DB::table('permisos')->where('modulo', 'egresos')->where('accion', self::ACCION)->value('id');

        DB::table('rol_permiso')->where('permiso_id', $permisoId)->delete();
        DB::table('usuario_permiso')->where('permiso_id', $permisoId)->delete();
        DB::table('permisos')->where('id', $permisoId)->delete();
    }
};
