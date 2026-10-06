<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** El rol contable también reversa pagos: lleva el módulo de cuentas por cobrar y el asiento de recaudo. */
return new class extends Migration
{
    public function up(): void
    {
        $permisoId = DB::table('permisos')->where('modulo', 'pagos')->where('accion', 'reversar')->value('id');

        foreach (DB::table('roles')->where('nombre', 'contable')->pluck('id') as $rolId) {
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
        $permisoId = DB::table('permisos')->where('modulo', 'pagos')->where('accion', 'reversar')->value('id');

        $rolIds = DB::table('roles')->where('nombre', 'contable')->pluck('id');

        DB::table('rol_permiso')->where('permiso_id', $permisoId)->whereIn('rol_id', $rolIds)->delete();
    }
};
