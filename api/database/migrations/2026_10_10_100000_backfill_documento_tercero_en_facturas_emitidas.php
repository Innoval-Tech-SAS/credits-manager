<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crea el `documento_tercero` (cuenta por cobrar) de las facturas emitidas antes de 2026_10_08_100200, que solo agregó
 * la columna: sin él la factura no aparece en la cartera por tercero ni se puede cruzar con cobros o anticipos.
 * Reproduce lo que hoy hacen la emisión y cada movimiento posterior: valor original = total de la factura, y una
 * aplicación por cobro, reversa y nota (crédito resta, débito suma), con el mismo `modulo_aplicacion` que usa el
 * Service. Montos en centavos, como el resto de Facturación y Documentos. En PHP (no INSERT...SELECT) por
 * compatibilidad con SQLite en tests.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('documentos_facturacion as f')
            ->join('clientes as c', 'c.id', '=', 'f.cliente_id')
            ->where('f.tipo', 'factura')
            ->where('f.estado', 'emitido')
            ->whereNull('f.documento_tercero_id')
            ->whereNull('f.borrado_en')
            ->whereNotNull('c.tercero_id')
            ->select('f.id', 'f.empresa_id', 'f.numero', 'f.fecha', 'f.fecha_vencimiento', 'c.tercero_id')
            ->orderBy('f.id')
            ->chunk(200, function ($facturas) {
                foreach ($facturas as $factura) {
                    DB::transaction(fn () => $this->crearDocumento($factura));
                }
            });
    }

    public function down(): void
    {
        // Dato derivado: una vez creado, el documento ya pudo recibir cobros o anticipos nuevos; no se revierte.
    }

    private function crearDocumento(object $factura): void
    {
        $total = $this->sumaLineas($factura->id);
        $ahora = now();

        $documentoId = DB::table('documentos_tercero')->insertGetId([
            'empresa_id' => $factura->empresa_id,
            'tercero_id' => $factura->tercero_id,
            'naturaleza' => 'por_cobrar',
            'plan_cuenta_id' => $this->cuentaCxc($factura->empresa_id),
            'tipo_documento' => 'factura',
            'numero_documento' => $factura->numero,
            'fecha' => $factura->fecha,
            'fecha_vencimiento' => $factura->fecha_vencimiento,
            'concepto' => "Factura {$factura->numero}",
            'valor_original' => $total,
            'saldo' => $total,
            'estado' => 'pendiente',
            'modulo_origen' => 'facturacion',
            'origen_id' => $factura->id,
            'creado_en' => $ahora,
            'ultima_actualizacion' => $ahora,
        ]);

        // Centavos que cada movimiento resta del saldo (negativo = lo aumenta), igual que `valor_aplicado`.
        $aplicaciones = [];

        foreach ($this->notas($factura) as $nota) {
            $valor = $this->sumaLineas($nota->id);

            if ($valor > 0) {
                $aplicaciones[] = ['facturacion_nota', $nota->id, $nota->tipo === 'nota_credito' ? $valor : -$valor, $nota->fecha];
            }
        }

        foreach (DB::table('cobros_facturacion')->where('documento_id', $factura->id)->orderBy('id')->get() as $cobro) {
            $aplicaciones[] = $cobro->tipo === 'cobro'
                ? ['facturacion_cobro', $cobro->id, (int) $cobro->valor, $cobro->fecha]
                : ['facturacion_cobro_reversa', $cobro->id, -(int) $cobro->valor, $cobro->fecha];
        }

        $saldo = $total;

        foreach ($aplicaciones as [$modulo, $aplicacionId, $valor, $fecha]) {
            DB::table('aplicaciones_documento')->insert([
                'documento_tercero_id' => $documentoId,
                'modulo_aplicacion' => $modulo,
                'aplicacion_id' => $aplicacionId,
                'valor_aplicado' => $valor,
                'fecha' => $fecha,
                'creado_en' => $ahora,
                'ultima_actualizacion' => $ahora,
            ]);

            $saldo -= $valor;
        }

        $saldo = max(0, $saldo);

        DB::table('documentos_tercero')->where('id', $documentoId)->update([
            'saldo' => $saldo,
            'estado' => match (true) {
                $saldo <= 0 => 'cancelado',
                $saldo < $total => 'abonado_parcial',
                default => 'pendiente',
            },
        ]);

        DB::table('documentos_facturacion')->where('id', $factura->id)->update(['documento_tercero_id' => $documentoId]);
    }

    /** Notas emitidas sobre la factura o sobre una de sus notas crédito: las mismas que hoy mueven su documento. */
    private function notas(object $factura)
    {
        $notasCredito = DB::table('documentos_facturacion')
            ->where('documento_origen_id', $factura->id)
            ->where('tipo', 'nota_credito')
            ->pluck('id');

        return DB::table('documentos_facturacion')
            ->where('empresa_id', $factura->empresa_id)
            ->whereIn('tipo', ['nota_credito', 'nota_debito'])
            ->where('estado', 'emitido')
            ->whereNull('borrado_en')
            ->where(fn ($q) => $q->where('documento_origen_id', $factura->id)->orWhereIn('documento_origen_id', $notasCredito))
            ->orderBy('emitido_en')
            ->orderBy('id')
            ->get(['id', 'tipo', 'fecha']);
    }

    private function sumaLineas(int $documentoId): int
    {
        return (int) DB::table('lineas_documento_facturacion')->where('documento_id', $documentoId)->whereNull('borrado_en')->sum('valor');
    }

    private function cuentaCxc(int $empresaId): ?int
    {
        return DB::table('reglas_contabilizacion')
            ->where('empresa_id', $empresaId)
            ->where('evento', 'factura_emision')
            ->where('activo', true)
            ->whereNull('borrado_en')
            ->value('cuenta_debito_id');
    }
};
