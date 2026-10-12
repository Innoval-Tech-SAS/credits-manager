<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Datos previos a `exigir_coherencia_de_rol_en_usuarios_table`: crea el rol Super Admin, se lo asigna a quien tenía
 * `es_superadmin` y apaga `es_global` en quien no es asesor (ahí nunca tuvo efecto). Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ahora = now();

        $rolId = DB::table('roles')->whereNull('empresa_id')->value('id')
            ?? DB::table('roles')->insertGetId([
                'empresa_id' => null,
                'nombre' => 'superadmin',
                'descripcion' => 'Super Admin',
                'creado_en' => $ahora,
                'ultima_actualizacion' => $ahora,
            ]);

        DB::table('usuarios')->where('es_superadmin', true)->update(['rol_id' => $rolId]);

        DB::table('usuarios')
            ->where('es_global', true)
            ->where(fn (Builder $consulta) => $consulta
                ->whereNull('rol_id')
                ->orWhereNotIn('rol_id', DB::table('roles')->select('id')->where('nombre', 'asesor')))
            ->update(['es_global' => false]);
    }

    public function down(): void
    {
        $rolId = DB::table('roles')->whereNull('empresa_id')->value('id');

        if ($rolId === null) {
            return;
        }

        DB::table('usuarios')->where('rol_id', $rolId)->update(['rol_id' => null]);
        DB::table('roles')->where('id', $rolId)->delete();
    }
};
