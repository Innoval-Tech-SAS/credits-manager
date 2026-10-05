<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permiso para enviar recordatorios de cobro por correo. Admin recibe todo el catálogo; cartera lo recibe
 * porque es quien hace la cobranza. Asesor no. La configuración del servicio (SMTP) es solo de admin.
 */
return new class extends Migration
{
    private const ROLES = ['admin', 'cartera'];

    public function up(): void
    {
        DB::table('permisos')->updateOrInsert(
            ['modulo' => 'cartera', 'accion' => 'enviar_recordatorio'],
            ['creado_en' => now(), 'ultima_actualizacion' => now()],
        );

        $permisoId = DB::table('permisos')->where('modulo', 'cartera')->where('accion', 'enviar_recordatorio')->value('id');

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
        $permisoId = DB::table('permisos')->where('modulo', 'cartera')->where('accion', 'enviar_recordatorio')->value('id');

        if ($permisoId === null) {
            return;
        }

        DB::table('rol_permiso')->where('permiso_id', $permisoId)->delete();
        DB::table('usuario_permiso')->where('permiso_id', $permisoId)->delete();
        DB::table('permisos')->where('id', $permisoId)->delete();
    }
};
