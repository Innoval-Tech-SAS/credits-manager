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

    /**
     * `$bruto` es la suma de `valor` de las líneas de la nota (sin impuestos); el IVA/retenciones prorrateados que
     * `DocumentoFacturacionService::lineasDeNotaCredito/lineasDeNotaDebito` ya dejaron en cada línea se leen de
     * `$nota->lineas` (debe venir cargada). Nota crédito reversa lo que la emisión causó: su regla ya tiene los
     * roles débito/crédito invertidos respecto a `FacturaEmision` (débito = ingreso, crédito = CxC — ver el
     * comentario de `EventoContable`), así que el IVA (crédito en la emisión) entra en débito y las retenciones
     * (débito en la emisión) entran en crédito. Nota débito mantiene los mismos roles y signos que la emisión:
     * aumenta CxC/ingreso/IVA en la misma dirección, no los reversa.
     */
    public function contabilizarNota(DocumentoFacturacion $nota, Dinero $bruto): Comprobante
    {
        $esNotaCredito = $nota->tipo === TipoDocumentoFacturacion::NotaCredito;
        $evento = $esNotaCredito ? EventoContable::NotaCreditoFactura : EventoContable::NotaDebitoFactura;
        $regla = $this->reglaActiva($evento);
        $parametrizaciones = $this->parametrizacionesConcepto->paraEmpresa($nota->empresa_id);

        $totalIva = $nota->lineas->reduce(fn (Dinero $acumulado, $linea) => $acumulado->sumar($linea->valor_iva), Dinero::cero());
        $totalRetencion = $nota->lineas->reduce(
            fn (Dinero $acumulado, $linea) => $acumulado->sumar($linea->valor_retefuente)->sumar($linea->valor_reteica)->sumar($linea->valor_reteiva),
            Dinero::cero(),
        );
        $neto = $bruto->sumar($totalIva)->restar($totalRetencion);

        $lineasComprobante = $esNotaCredito
            ? [
                ['cuenta_id' => $regla->cuenta_debito_id, 'debito' => $bruto, 'credito' => 0],
                ['cuenta_id' => $regla->cuenta_credito_id, 'debito' => 0, 'credito' => $neto],
            ]
            : [
                ['cuenta_id' => $regla->cuenta_debito_id, 'debito' => $neto, 'credito' => 0],
                ['cuenta_id' => $regla->cuenta_credito_id, 'debito' => 0, 'credito' => $bruto],
            ];

        foreach ($this->desglosePorCuenta($nota, $parametrizaciones, 'valor_iva', 'cuenta_iva_id') as $cuentaId => $valor) {
            $lineasComprobante[] = $esNotaCredito
                ? ['cuenta_id' => $cuentaId, 'debito' => $valor, 'credito' => 0]
                : ['cuenta_id' => $cuentaId, 'debito' => 0, 'credito' => $valor];
        }

        foreach ([
            'valor_retefuente' => 'cuenta_retefuente_id',
            'valor_reteica' => 'cuenta_reteica_id',
            'valor_reteiva' => 'cuenta_reteiva_id',
        ] as $campoValor => $campoCuenta) {
            foreach ($this->desglosePorCuenta($nota, $parametrizaciones, $campoValor, $campoCuenta) as $cuentaId => $valor) {
                $lineasComprobante[] = $esNotaCredito
                    ? ['cuenta_id' => $cuentaId, 'debito' => 0, 'credito' => $valor]
                    : ['cuenta_id' => $cuentaId, 'debito' => $valor, 'credito' => 0];
            }
        }

        return $this->comprobantes->crearComprobante(
            $regla->tipo_comprobante_id,
            $nota->fecha->toDateString(),
            $lineasComprobante,
            "{$nota->tipo->etiqueta()} {$nota->numero}",
            'facturacion',
            $nota->id,
            null,
            $nota->cliente?->tercero_id,
        );
    }

    /** El cobro se asienta contra el documento (la factura), no contra la fila del cobro, que aún no existe al contabilizar. */
    public function contabilizarCobro(DocumentoFacturacion $factura, Dinero $valor, string $fecha): Comprobante
    {
        return $this->contabilizar(EventoContable::CobroFactura, $factura, $valor, $fecha, "Cobro de factura {$factura->numero}", 'facturacion_cobro', $factura->id);
    }

    /**
     * Cruza una factura de intereses ya recaudados: debita la cuenta de pasivo donde quedó ese interés al pagarse
     * (`RecaudoInteres`, reparametrizada para no reconocer ingreso en ese momento — ver
     * `DocumentoFacturacionService::facturarInteresesPagados`) y acredita la misma cuenta CxC que debitó la
     * emisión de esta factura, dejándola en cero sin tocar banco otra vez: el efectivo ya entró cuando se pagó.
     */
    public function contabilizarCruceInteresFacturado(DocumentoFacturacion $factura, Dinero $valor, string $fecha): Comprobante
    {
        return $this->contabilizar(EventoContable::CruceInteresFacturado, $factura, $valor, $fecha, "Cruce de interés ya recaudado contra la factura {$factura->numero}", 'facturacion_cruce_interes', $factura->id);
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
