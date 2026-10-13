<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mantiene el mismo `permiso_id` de producción y solo corrige la clave de acción del catálogo para reflejar
 * el significado real: emitir una nota, no anularla. Como los roles y excepciones de usuario apuntan al `id`
 * existente, no se altera la asignación ni se pierden permisos.
 */
return new class extends Migration
{
    private const RENOMBRADOS = [
        'anular_nota_credito' => 'emitir_nota_credito',
        'anular_nota_debito' => 'emitir_nota_debito',
    ];

    public function up(): void
    {
        foreach (self::RENOMBRADOS as $accionAnterior => $accionNueva) {
            DB::table('permisos')
                ->where('modulo', 'facturacion')
                ->where('accion', $accionAnterior)
                ->update(['accion' => $accionNueva]);
        }
    }

    public function down(): void
    {
        foreach (array_flip(self::RENOMBRADOS) as $accionAnterior => $accionNueva) {
            DB::table('permisos')
                ->where('modulo', 'facturacion')
                ->where('accion', $accionAnterior)
                ->update(['accion' => $accionNueva]);
        }
    }
};
