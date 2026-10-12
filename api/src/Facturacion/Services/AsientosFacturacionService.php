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
use App\Facturacion\Models\ParametrizacionConceptoFacturacion;
use App\Facturacion\Repositories\ParametrizacionConceptoFacturacionRepository;
use Illuminate\Support\Collection;
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
        protected ParametrizacionConceptoFacturacionRepository $parametrizacionesConcepto,
        protected ComprobanteService $comprobantes,
    ) {}

    /**
     * CxC = neto (lo que debe el cliente); Ingreso = bruto de las líneas; IVA e impuestos retenidos salen de la
     * cuenta parametrizada por tipo de concepto (`ParametrizacionConceptoFacturacion`), agrupados si varias líneas
     * comparten cuenta. Con impuestos en cero (el caso de hoy) es exactamente el asiento de dos líneas de siempre.
     *
     * @param  array{subtotal: Dinero, total: Dinero, total_iva?: Dinero}  $totales
     */
    public function contabilizarEmision(DocumentoFacturacion $factura, array $totales): Comprobante
    {
        $regla = $this->reglaActiva(EventoContable::FacturaEmision);
        $parametrizaciones = $this->parametrizacionesConcepto->paraEmpresa($factura->empresa_id);

        $lineasComprobante = [
            ['cuenta_id' => $regla->cuenta_debito_id, 'debito' => $totales['total'], 'credito' => 0],
            ['cuenta_id' => $regla->cuenta_credito_id, 'debito' => 0, 'credito' => $totales['subtotal']],
        ];

        foreach ($this->desglosePorCuenta($factura, $parametrizaciones, 'valor_iva', 'cuenta_iva_id') as $cuentaId => $valor) {
            $lineasComprobante[] = ['cuenta_id' => $cuentaId, 'debito' => 0, 'credito' => $valor];
        }

        foreach ([
            'valor_retefuente' => 'cuenta_retefuente_id',
            'valor_reteica' => 'cuenta_reteica_id',
            'valor_reteiva' => 'cuenta_reteiva_id',
        ] as $campoValor => $campoCuenta) {
            foreach ($this->desglosePorCuenta($factura, $parametrizaciones, $campoValor, $campoCuenta) as $cuentaId => $valor) {
                $lineasComprobante[] = ['cuenta_id' => $cuentaId, 'debito' => $valor, 'credito' => 0];
            }
        }

        return $this->comprobantes->crearComprobante(
            $regla->tipo_comprobante_id,
            $factura->fecha->toDateString(),
            $lineasComprobante,
            "Factura {$factura->numero}",
            'facturacion',
            $factura->id,
            null,
            $factura->cliente?->tercero_id,
        );
    }

    /**
     * Suma por cuenta contable el campo de impuesto indicado entre las líneas de la factura, usando la cuenta que
     * la parametrización del tipo de concepto de cada línea tiene configurada. Sin impuesto en ninguna línea,
     * devuelve un arreglo vacío (ninguna línea de más en el comprobante).
     *
     * @param  Collection<string, ParametrizacionConceptoFacturacion>  $parametrizaciones
     * @return array<int, Dinero>
     */
    private function desglosePorCuenta(DocumentoFacturacion $factura, Collection $parametrizaciones, string $campoValor, string $campoCuenta): array
    {
        $acumulado = [];

        foreach ($factura->lineas as $linea) {
            $valor = $linea->{$campoValor};

            if (! $valor instanceof Dinero || ! $valor->esPositivo() || $linea->tipo_concepto === null) {
                continue;
            }

            $cuentaId = $parametrizaciones->get($linea->tipo_concepto->value)?->{$campoCuenta};

            if ($cuentaId === null) {
                throw ValidationException::withMessages([
                    'contabilidad' => __('validation.custom.facturacion.sin_cuenta_impuesto', ['concepto' => $linea->tipo_concepto->etiqueta()]),
                ]);
            }

            $acumulado[$cuentaId] = ($acumulado[$cuentaId] ?? Dinero::cero())->sumar($valor);
        }

        return $acumulado;
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

    /** Cuenta PUC que debita la emisión de la factura (la cuenta por cobrar): la misma que muestra la cartera por tercero. */
    public function cuentaCxcFactura(): int
    {
        return $this->reglaActiva(EventoContable::FacturaEmision)->cuenta_debito_id;
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
