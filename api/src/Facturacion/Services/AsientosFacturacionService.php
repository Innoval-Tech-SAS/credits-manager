<?php

namespace App\Facturacion\Services;

use App\Contabilidad\Enums\EventoContable;
use App\Contabilidad\Models\Comprobante;
use App\Contabilidad\Models\ReglaContabilizacion;
use App\Contabilidad\Repositories\ReglaContabilizacionRepository;
use App\Contabilidad\Services\ComprobanteService;
use App\Core\Dinero\Dinero;
use App\Facturacion\Enums\TipoDocumentoFacturacion;
use App\Facturacion\Models\DocumentoFacturacion;
use Illuminate\Validation\ValidationException;

/**
 * Asientos de la facturación. Cada evento usa su regla de contabilización; si la regla no existe, la operación se
 * rechaza (nunca se emite ni se cobra en silencio sin asiento). El asiento se crea dentro de la transacción del
 * documento o del cobro, así que un descuadre o un período cerrado deshace también la operación.
 */
class AsientosFacturacionService
{
    public function __construct(
        protected ReglaContabilizacionRepository $reglas,
        protected ComprobanteService $comprobantes,
    ) {}

    public function contabilizarEmision(DocumentoFacturacion $factura, Dinero $total): Comprobante
    {
        return $this->contabilizar(EventoContable::FacturaEmision, $factura, $total, $factura->fecha->toDateString(), "Factura {$factura->numero}", 'facturacion', $factura->id);
    }

    public function contabilizarNota(DocumentoFacturacion $nota, Dinero $total): Comprobante
    {
        $evento = $nota->tipo === TipoDocumentoFacturacion::NotaCredito ? EventoContable::NotaCreditoFactura : EventoContable::NotaDebitoFactura;

        return $this->contabilizar($evento, $nota, $total, $nota->fecha->toDateString(), "{$nota->tipo->etiqueta()} {$nota->numero}", 'facturacion', $nota->id);
    }

    /** El cobro se asienta contra el documento (la factura), no contra la fila del cobro, que aún no existe al contabilizar. */
    public function contabilizarCobro(DocumentoFacturacion $factura, Dinero $valor, string $fecha): Comprobante
    {
        return $this->contabilizar(EventoContable::CobroFactura, $factura, $valor, $fecha, "Cobro de factura {$factura->numero}", 'facturacion_cobro', $factura->id);
    }

    private function contabilizar(EventoContable $evento, DocumentoFacturacion $documento, Dinero $valor, string $fecha, string $concepto, string $modulo, int $origenId): Comprobante
    {
        $regla = $this->reglaActiva($evento);

        return $this->comprobantes->crearComprobante(
            $regla->tipo_comprobante_id,
            $fecha,
            [
                ['cuenta_id' => $regla->cuenta_debito_id, 'debito' => $valor, 'credito' => 0],
                ['cuenta_id' => $regla->cuenta_credito_id, 'debito' => 0, 'credito' => $valor],
            ],
            $concepto,
            $modulo,
            $origenId,
            null,
            $documento->cliente?->tercero_id,
        );
    }

    private function reglaActiva(EventoContable $evento): ReglaContabilizacion
    {
        $regla = $this->reglas->activaParaEvento($evento);

        if ($regla === null) {
            throw ValidationException::withMessages([
                'contabilidad' => __('validation.custom.facturacion.sin_regla_contable', ['evento' => $evento->etiqueta()]),
            ]);
        }

        return $regla;
    }
}
