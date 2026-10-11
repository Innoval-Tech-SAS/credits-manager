<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Notas crédito y notas débito piden permiso propio, separado de `facturacion.anular` (que sigue siendo la
 * anulación de la factura completa). Quien ya tenía `facturacion.anular` recibe los dos nuevos en roles y en
 * excepciones por usuario (con el mismo `concedido`): nadie pierde acceso al desplegar. `anular` no se toca ni se
 * quita, el Controller ya no la exige para `POST .../notas` (ahora pide anular_nota_credito/anular_nota_debito
 * según el tipo recibido en el body), pero sigue siendo la que exige `POST .../anular`.
 */
return new class extends Migration
{
    private const ACCIONES_NUEVAS = ['anular_nota_credito', 'anular_nota_debito'];

    public function up(): void
    {
        foreach (self::ACCIONES_NUEVAS as $accion) {
            DB::table('permisos')->insertOrIgnore([
                'modulo' => 'facturacion',
                'accion' => $accion,
                'creado_en' => now(),
                'ultima_actualizacion' => now(),
            ]);
        }

        $nuevos = DB::table('permisos')
            ->where('modulo', 'facturacion')
            ->whereIn('accion', self::ACCIONES_NUEVAS)
            ->pluck('id', 'accion');

        $origenId = DB::table('permisos')->where(['modulo' => 'facturacion', 'accion' => 'anular'])->value('id');

        if ($origenId === null) {
            return;
        }

        foreach (DB::table('rol_permiso')->where('permiso_id', $origenId)->pluck('rol_id') as $rolId) {
            foreach ($nuevos as $nuevoId) {
                DB::table('rol_permiso')->insertOrIgnore([
                    'rol_id' => $rolId,
                    'permiso_id' => $nuevoId,
                    'creado_en' => now(),
                    'ultima_actualizacion' => now(),
                ]);
            }
        }

        foreach (DB::table('usuario_permiso')->where('permiso_id', $origenId)->get(['usuario_id', 'concedido']) as $excepcion) {
            foreach ($nuevos as $nuevoId) {
                DB::table('usuario_permiso')->insertOrIgnore([
                    'usuario_id' => $excepcion->usuario_id,
                    'permiso_id' => $nuevoId,
                    'concedido' => $excepcion->concedido,
                    'creado_en' => now(),
                    'ultima_actualizacion' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $permisoIds = DB::table('permisos')
            ->where('modulo', 'facturacion')
            ->whereIn('accion', self::ACCIONES_NUEVAS)
            ->pluck('id');

        DB::table('rol_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('usuario_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('permisos')->whereIn('id', $permisoIds)->delete();
    }
};
