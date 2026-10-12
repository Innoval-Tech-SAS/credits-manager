<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permiso nuevo para alterar el valor sugerido de una línea de factura (el que viene del plan de cuotas).
 * Mismo patrón que `separar_permiso_anular_notas_facturacion`: quien ya tenía `facturacion.crear` lo recibe
 * en roles y excepciones por usuario, con el mismo `concedido` — nadie pierde ni gana acceso de más al desplegar.
 */
return new class extends Migration
{
    private const ACCION_NUEVA = 'modificar_valores_sugeridos';

    public function up(): void
    {
        DB::table('permisos')->insertOrIgnore([
            'modulo' => 'facturacion',
            'accion' => self::ACCION_NUEVA,
            'creado_en' => now(),
            'ultima_actualizacion' => now(),
        ]);

        $nuevoId = DB::table('permisos')->where(['modulo' => 'facturacion', 'accion' => self::ACCION_NUEVA])->value('id');
        $origenId = DB::table('permisos')->where(['modulo' => 'facturacion', 'accion' => 'crear'])->value('id');

        if ($nuevoId === null || $origenId === null) {
            return;
        }

        foreach (DB::table('rol_permiso')->where('permiso_id', $origenId)->pluck('rol_id') as $rolId) {
            DB::table('rol_permiso')->insertOrIgnore([
                'rol_id' => $rolId,
                'permiso_id' => $nuevoId,
                'creado_en' => now(),
                'ultima_actualizacion' => now(),
            ]);
        }

        foreach (DB::table('usuario_permiso')->where('permiso_id', $origenId)->get(['usuario_id', 'concedido']) as $excepcion) {
            DB::table('usuario_permiso')->insertOrIgnore([
                'usuario_id' => $excepcion->usuario_id,
                'permiso_id' => $nuevoId,
                'concedido' => $excepcion->concedido,
                'creado_en' => now(),
                'ultima_actualizacion' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $permisoId = DB::table('permisos')->where(['modulo' => 'facturacion', 'accion' => self::ACCION_NUEVA])->value('id');

        if ($permisoId === null) {
            return;
        }

        DB::table('rol_permiso')->where('permiso_id', $permisoId)->delete();
        DB::table('usuario_permiso')->where('permiso_id', $permisoId)->delete();
        DB::table('permisos')->where('id', $permisoId)->delete();
    }
};
