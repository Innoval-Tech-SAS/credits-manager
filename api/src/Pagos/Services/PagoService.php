<?php

namespace App\Pagos\Services;

use App\Contabilidad\Services\ComprobanteService;
use App\Core\Dinero\Dinero;
use App\Creditos\Enums\EstadoCredito;
use App\Creditos\Enums\EstadoCuota;
use App\Creditos\Enums\TipoPago;
use App\Creditos\Models\Credito;
use App\Creditos\Repositories\CreditoRepository;
use App\Creditos\Repositories\CuotaRepository;
use App\Pagos\Enums\FormaPago;
use App\Pagos\Repositories\PagoDetalleRepository;
use App\Pagos\Repositories\PagoRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PagoService
{
    public function __construct(
        protected PagoRepository $pagos,
        protected PagoDetalleRepository $pagoDetalles,
        protected CuotaRepository $cuotas,
        protected CreditoRepository $creditos,
        protected ComprobanteService $comprobantes,
    ) {}

    public function listarPorCredito(int $creditoId, string $direccion = 'desc'): Collection
    {
        return $this->pagos->porCredito($creditoId, $direccion);
    }

    public function listar(?string $busqueda, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->pagos->paginar($busqueda, max(1, min(100, $porPagina)), max(1, $pagina));
    }

    /**
     * Aplica el pago a las cuotas pendientes/vencidas del crédito, de la más antigua
     * a la más reciente (misma regla que `registrarPago` del legado). El sobrante,
     * si el pago excede lo adeudado, se descarta silenciosamente — no se guarda ni
     * se abona a nada (quirk del legado, se replica tal cual).
     */
    public function registrarPago(Credito $credito, Dinero $valorPago, FormaPago $formaPago, ?string $observacion, ?int $usuarioId): array
    {
        $this->exigirDesembolsado($credito);

        return DB::transaction(function () use ($credito, $valorPago, $formaPago, $observacion, $usuarioId) {
            $pago = $this->pagos->crear([
                'credito_id' => $credito->id,
                'cliente_id' => $credito->cliente_id,
                'valor_pago' => $valorPago,
                'fecha_pago' => now(),
                'forma_pago' => $formaPago,
                'observacion' => $observacion,
                'registrado_por' => $usuarioId,
            ]);

            $restante = $valorPago;
            $capitalTotal = Dinero::cero();
            $interesTotal = Dinero::cero();

            foreach ($this->cuotas->pendientesOrdenadasParaActualizar($credito->id) as $cuota) {
                if (! $restante->esPositivo()) {
                    break;
                }

                $saldoCuota = $cuota->valor->restar($cuota->valor_pagado);
                if (! $saldoCuota->esPositivo()) {
                    continue;
                }

                $aplicar = $restante->menorEntre($saldoCuota);
                $nuevoPagado = $cuota->valor_pagado->sumar($aplicar);

                // Interés primero: la cuota ya trae fijo su valor_interes/valor_capital
                // (AmortizacionCalculator); pago_detalle no guarda ese desglose por línea
                // (a diferencia del legado), así que se deriva aquí para la contabilización.
                $interesYaPagado = $cuota->valor_pagado->menorEntre($cuota->valor_interes);
                $interesPendiente = $cuota->valor_interes->restar($interesYaPagado);
                $interesEnEstePago = $aplicar->menorEntre($interesPendiente->noNegativo());
                $capitalEnEstePago = $aplicar->restar($interesEnEstePago);
                $capitalTotal = $capitalTotal->sumar($capitalEnEstePago);
                $interesTotal = $interesTotal->sumar($interesEnEstePago);

                $this->pagoDetalles->crear([
                    'pago_id' => $pago->id,
                    'cuota_id' => $cuota->id,
                    'valor_aplicado' => $aplicar,
                ]);

                $this->cuotas->actualizar($cuota, [
                    'valor_pagado' => $nuevoPagado,
                    'estado' => $nuevoPagado->mayorOIgualQue($cuota->valor) ? EstadoCuota::Pagada : EstadoCuota::Pendiente,
                ]);

                $restante = $restante->restar($aplicar);
            }

            $this->recalcularSaldoCapital($credito);

            if ($this->cuotas->contarNoPagadas($credito->id) === 0) {
                $this->creditos->marcarComoPagado($credito);
            }

            $this->comprobantes->contabilizarRecaudoPago($pago, $capitalTotal, $interesTotal);

            return ['pago' => $pago, 'sobrante' => $restante];
        });
    }

    /**
     * Cotiza el pago total anticipado del crédito (Ley 1555 de 2012: prepago sin
     * penalización, con "liquidación de intereses al día del pago"). Reemplaza por
     * completo el interés fijo del cronograma por un interés simple prorrateado por
     * días transcurridos desde el último pago (o desde el desembolso, si aún no ha
     * pagado nada) — los días transcurridos ya incluyen cualquier atraso, así que la
     * mora queda cubierta sin contarla dos veces. Pendiente de confirmar con el
     * cliente; ver alternativa híbrida comentada más abajo.
     */
    public function calcularLiquidacion(Credito $credito): array
    {
        $this->exigirDesembolsado($credito);

        $capitalPendiente = $this->capitalPendienteReal($credito);

        $fechaReferencia = $this->pagos->ultimoPago($credito->id)?->fecha_pago ?? $credito->fecha_desembolso;
        $diasTranscurridos = (int) $fechaReferencia->diffInDays(now());

        $divisorPeriodo = $credito->tipo_pago === TipoPago::Mensual ? 30 : 15;
        $tasaDiaria = ((float) $credito->tasa_interes / 100) / $divisorPeriodo;

        $interes = $capitalPendiente->multiplicarPorTasa($tasaDiaria * $diasTranscurridos);
        $total = $capitalPendiente->sumar($interes);

        /*
         * Alternativa híbrida (comentada, pendiente de confirmación del cliente):
         * en vez de reemplazar todo el interés del cronograma, cobrar el valor ya
         * fijado de las cuotas vencidas/pendientes (capital + interés tal cual, porque
         * ese período ya se cumplió) más el capital de las cuotas futuras (sin su
         * interés fijo, porque aún no se cumple el período) más el interés prorrateado
         * solo del período en curso (desde el vencimiento de la última cuota ya
         * cubierta, o desde el desembolso si aún no vence la primera).
         *
         * $fechaReferenciaHibrida = $credito->cuotas()
         *     ->where('estado', EstadoCuota::Pagada)
         *     ->orderByDesc('numero_cuota')
         *     ->value('fecha_vencimiento') ?? $credito->fecha_desembolso;
         * $diasTranscurridosHibrida = (int) $fechaReferenciaHibrida->diffInDays(now());
         *
         * $valorCuotasNoFuturas = $credito->cuotas()
         *     ->whereIn('estado', [EstadoCuota::Pendiente, EstadoCuota::Vencida])
         *     ->where('fecha_vencimiento', '<=', now())
         *     ->sum(DB::raw('valor - valor_pagado'));
         * $capitalCuotasFuturas = $credito->cuotas()
         *     ->where('fecha_vencimiento', '>', now())
         *     ->sum('valor_capital');
         *
         * $interesHibrido = round($capitalPendiente * $tasaDiaria * $diasTranscurridosHibrida, 2);
         * $totalHibrido = round($valorCuotasNoFuturas + $capitalCuotasFuturas + $interesHibrido, 2);
         */

        return [
            'capital_pendiente' => $capitalPendiente,
            'dias_transcurridos' => $diasTranscurridos,
            'tasa_diaria' => $tasaDiaria,
            'interes' => $interes,
            'total' => $total,
            'fecha_referencia' => $fechaReferencia->toDateString(),
        ];
    }

    public function liquidarCredito(Credito $credito, FormaPago $formaPago, ?string $observacion, ?int $usuarioId): array
    {
        return DB::transaction(function () use ($credito, $formaPago, $observacion, $usuarioId) {
            $cotizacion = $this->calcularLiquidacion($credito);

            $pago = $this->pagos->crear([
                'credito_id' => $credito->id,
                'cliente_id' => $credito->cliente_id,
                'valor_pago' => $cotizacion['total'],
                'fecha_pago' => now(),
                'forma_pago' => $formaPago,
                'observacion' => $observacion ?? 'Liquidación anticipada del crédito',
                'registrado_por' => $usuarioId,
            ]);

            $this->cuotas->cerrarPendientesComoOrigen($credito->id);
            $this->recalcularSaldoCapital($credito);
            $this->creditos->marcarComoPagado($credito);
            $this->comprobantes->contabilizarRecaudoPago($pago, $cotizacion['capital_pendiente'], $cotizacion['interes']);

            return ['pago' => $pago, ...$cotizacion];
        });
    }

    private function exigirDesembolsado(Credito $credito): void
    {
        if ($credito->estado !== EstadoCredito::Desembolsado) {
            throw ValidationException::withMessages(['estado' => __('validation.custom.estado.no_desembolsado')]);
        }
    }

    /**
     * Capital realmente pendiente de todo el crédito (no de una cuota puntual), a partir
     * de lo efectivamente pagado (`valor_pagado - valor_interes`), no del cronograma
     * teórico que genera `AmortizacionCalculator`.
     */
    private function capitalPendienteReal(Credito $credito): Dinero
    {
        $capitalRestante = $credito->valor_prestamo;

        foreach ($this->cuotas->todasPorCredito($credito->id) as $cuota) {
            $capitalRestante = $capitalRestante->restar($cuota->valor_pagado->restar($cuota->valor_interes)->noNegativo());
        }

        return $capitalRestante->noNegativo();
    }

    /**
     * Recalcula `saldo_capital` de todas las cuotas del crédito con la misma derivación
     * que `capitalPendienteReal`, pero persistiendo el resultado cuota por cuota. Necesario
     * porque un abono parcial no amortiza capital al ritmo del cronograma, y el saldo
     * mostrado quedaría "a favor" del deudor si se dejara el valor fijo del desembolso.
     */
    private function recalcularSaldoCapital(Credito $credito): void
    {
        $capitalRestante = $credito->valor_prestamo;

        foreach ($this->cuotas->todasOrdenadasParaActualizar($credito->id) as $cuota) {
            $capitalPagado = $cuota->valor_pagado->restar($cuota->valor_interes)->noNegativo();
            $capitalRestante = $capitalRestante->restar($capitalPagado);
            $this->cuotas->actualizar($cuota, ['saldo_capital' => $capitalRestante->noNegativo()]);
        }
    }
}
