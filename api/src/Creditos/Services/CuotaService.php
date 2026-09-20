<?php

namespace App\Creditos\Services;

use App\Core\Dinero\Dinero;
use App\Creditos\Repositories\CuotaRepository;

class CuotaService
{
    public function __construct(protected CuotaRepository $cuotas) {}

    public function actualizarVencidas(): void
    {
        $this->cuotas->marcarVencidas();
    }

    public function saldoCredito(int $creditoId): Dinero
    {
        return Dinero::desdeCentavos($this->cuotas->saldoPendiente($creditoId));
    }

    public function estadoCartera(int $creditoId): string
    {
        return $this->cuotas->contarVencidas($creditoId) > 0 ? 'En mora' : 'Al día';
    }

    public function diasMora(int $creditoId): int
    {
        return $this->diasMoraDesdeFecha($this->cuotas->primeraFechaVencida($creditoId));
    }

    public function diasMoraDesdeFecha(?string $fecha): int
    {
        if (! $fecha) {
            return 0;
        }

        $dias = (strtotime(now()->toDateString()) - strtotime($fecha)) / 86400;

        return (int) $dias;
    }

    /**
     * Agregados "al día" para la vista de detalle de un crédito: mismo orden de
     * aplicación de pago (interés primero, luego capital) que
     * `PagoService::registrarPago`/`capitalPendienteReal`, más la curva teórica de
     * `AmortizacionCalculator` (a partir de `valor_capital`, fijo por cuota y nunca
     * reescrito por un pago) para el plan de amortización — a diferencia de
     * `saldo_capital`, que sí se recalcula tras cada abono y por eso deja de servir
     * como curva una vez hay pagos parciales.
     */
    public function resumenAlDia(int $creditoId, Dinero $valorPrestamo): array
    {
        $interesPendiente = Dinero::cero();
        $capitalPagado = Dinero::cero();
        $saldoCapitalTeorico = $valorPrestamo;
        $curvaPorCuota = [];

        foreach ($this->cuotas->todasPorCredito($creditoId) as $cuota) {
            $valorInteres = $cuota->valor_interes;
            $valorCapital = $cuota->valor_capital;
            $valorPagado = $cuota->valor_pagado;

            $interesPagado = $valorPagado->menorEntre($valorInteres);
            $interesPendiente = $interesPendiente->sumar($valorInteres)->restar($interesPagado);
            $capitalPagado = $capitalPagado->sumar($valorPagado->restar($valorInteres)->noNegativo());

            $saldoCapitalTeorico = $saldoCapitalTeorico->restar($valorCapital)->noNegativo();
            $curvaPorCuota[$cuota->numero_cuota] = $saldoCapitalTeorico;
        }

        return [
            'interes_pendiente' => $interesPendiente,
            'saldo_capital_pendiente' => $valorPrestamo->restar($capitalPagado)->noNegativo(),
            'saldo_capital_pagado' => $capitalPagado,
            'saldo_capital_teorico_por_cuota' => $curvaPorCuota,
        ];
    }
}
