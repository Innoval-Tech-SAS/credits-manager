<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reversa de pagos (solo admin: revertir un pago mueve saldos y asientos) y consulta de recibos (admin y contable).
 * El catálogo y PERMISOS_POR_ROL solo aplican al crear una empresa, así que se conceden también a los roles existentes.
 */
return new class extends Migration
{
    private const PERMISOS = [
        'pagos' => ['reversar'],
        'recibos' => ['listar'],
    ];

    private const ROLES_POR_PERMISO = [
        'pagos.reversar' => ['admin'],
        'recibos.listar' => ['admin', 'contable'],
    ];

    public function up(): void
    {
        foreach (self::PERMISOS as $modulo => $acciones) {
            foreach ($acciones as $accion) {
                DB::table('permisos')->updateOrInsert(
                    ['modulo' => $modulo, 'accion' => $accion],
                    ['creado_en' => now(), 'ultima_actualizacion' => now()],
                );
            }
        }

        foreach (self::ROLES_POR_PERMISO as $clave => $roles) {
            [$modulo, $accion] = explode('.', $clave);
            $permisoId = DB::table('permisos')->where('modulo', $modulo)->where('accion', $accion)->value('id');

            foreach (DB::table('roles')->whereIn('nombre', $roles)->pluck('id') as $rolId) {
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
            ->where(fn ($consulta) => $consulta->where('modulo', 'pagos')->where('accion', 'reversar'))
            ->orWhere(fn ($consulta) => $consulta->where('modulo', 'recibos')->where('accion', 'listar'))
            ->pluck('id');

        DB::table('rol_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('usuario_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('permisos')->whereIn('id', $permisoIds)->delete();
    }
};
