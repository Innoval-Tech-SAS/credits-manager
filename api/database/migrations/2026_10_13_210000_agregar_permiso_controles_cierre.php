<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Mismos roles que ya ven conciliación de apertura: admin, contable, consulta y auditoría. */
return new class extends Migration
{
    private const ROLES = ['admin', 'contable', 'consulta', 'auditoria'];

    public function up(): void
    {
        DB::table('permisos')->updateOrInsert(
            ['modulo' => 'informes_contables', 'accion' => 'listar_controles_cierre'],
            ['creado_en' => now(), 'ultima_actualizacion' => now()],
        );

        $permisoId = DB::table('permisos')->where('modulo', 'informes_contables')->where('accion', 'listar_controles_cierre')->value('id');

        foreach (DB::table('roles')->whereIn('nombre', self::ROLES)->pluck('id') as $rolId) {
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
        $permisoId = DB::table('permisos')->where('modulo', 'informes_contables')->where('accion', 'listar_controles_cierre')->value('id');

        DB::table('rol_permiso')->where('permiso_id', $permisoId)->delete();
        DB::table('usuario_permiso')->where('permiso_id', $permisoId)->delete();
        DB::table('permisos')->where('id', $permisoId)->delete();
    }
};
