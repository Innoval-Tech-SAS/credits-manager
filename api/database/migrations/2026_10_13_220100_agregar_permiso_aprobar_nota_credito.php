<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Mismo rol que ya emite notas crédito: contable. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('permisos')->updateOrInsert(
            ['modulo' => 'facturacion', 'accion' => 'aprobar_nota_credito'],
            ['creado_en' => now(), 'ultima_actualizacion' => now()],
        );

        $permisoId = DB::table('permisos')->where('modulo', 'facturacion')->where('accion', 'aprobar_nota_credito')->value('id');

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
        $permisoId = DB::table('permisos')->where('modulo', 'facturacion')->where('accion', 'aprobar_nota_credito')->value('id');

        DB::table('rol_permiso')->where('permiso_id', $permisoId)->delete();
        DB::table('usuario_permiso')->where('permiso_id', $permisoId)->delete();
        DB::table('permisos')->where('id', $permisoId)->delete();
    }
};
