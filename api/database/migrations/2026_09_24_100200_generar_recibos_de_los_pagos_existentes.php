<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cada pago ya registrado recibe su recibo de caja, numerado por empresa en el orden en que se hicieron los pagos,
 * enlazado al comprobante que lo contabilizó si existe. Corre sin sesión, así que el `empresa_id` va explícito.
 */
return new class extends Migration
{
    public function up(): void
    {
        $comprobantePorPago = DB::table('comprobantes')
            ->where('modulo_origen', 'pagos')
            ->orderBy('id')
            ->get(['id', 'empresa_id', 'origen_id'])
            ->unique(fn (object $comprobante) => "{$comprobante->empresa_id}-{$comprobante->origen_id}")
            ->mapWithKeys(fn (object $comprobante) => ["{$comprobante->empresa_id}-{$comprobante->origen_id}" => $comprobante->id]);

        $ahora = now();

        foreach (DB::table('empresas')->pluck('id') as $empresaId) {
            $ultimo = 0;

            foreach (DB::table('pagos')->where('empresa_id', $empresaId)->whereNull('borrado_en')->orderBy('id')->get(['id', 'registrado_por', 'creado_en']) as $pago) {
                $ultimo++;

                DB::table('recibos_caja')->insert([
                    'empresa_id' => $empresaId,
                    'pago_id' => $pago->id,
                    'comprobante_id' => $comprobantePorPago["{$empresaId}-{$pago->id}"] ?? null,
                    'consecutivo' => $ultimo,
                    'numero' => "RC {$ultimo}",
                    'estado' => 'emitido',
                    'emitido_por' => $pago->registrado_por,
                    'creado_en' => $pago->creado_en ?? $ahora,
                    'ultima_actualizacion' => $ahora,
                ]);
            }

            if ($ultimo > 0) {
                DB::table('consecutivos_documento')->insert([
                    'empresa_id' => $empresaId,
                    'tipo_documento' => 'recibo_caja',
                    'prefijo' => 'RC',
                    'ultimo_numero' => $ultimo,
                    'creado_en' => $ahora,
                    'ultima_actualizacion' => $ahora,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Los recibos y el contador se eliminan al revertir las migraciones que crean sus tablas.
    }
};
