<?php

namespace App\Calendario\Services;

use App\Creditos\Enums\EstadoCuota;
use App\Creditos\Models\Cuota;
use App\Creditos\Repositories\CuotaRepository;
use App\Creditos\Services\CuotaService;
use Carbon\Carbon;

/**
 * Sin tabla propia — muestra todas las cuotas (pagadas o no) del mes que el calendario
 * tiene abierto, cada una con el color que le corresponde pintar en el front. El color
 * se decide aquí y no en el cliente para que la regla de negocio (qué cuenta como
 * vencida) viva en un solo lugar, la misma que ya usa `CuotaRepository::marcarVencidas`.
 */
class CalendarioService
{
    public function __construct(
        protected CuotaService $cuotaService,
        protected CuotaRepository $cuotas,
    ) {}

    /**
     * @param  string|null  $mes  Formato `YYYY-MM`. Si no se indica, usa el mes actual.
     */
    public function agenda(?string $mes = null): array
    {
        $this->cuotaService->actualizarVencidas();

        $inicioMes = $mes ? Carbon::createFromFormat('Y-m', $mes)->startOfMonth() : now()->startOfMonth();
        $desde = $inicioMes->toDateString();
        $hasta = $inicioMes->copy()->endOfMonth()->toDateString();

        return [
            'cuotas' => $this->cuotas->delMesDeCreditosActivos($desde, $hasta)
                ->map(fn (Cuota $cuota) => [
                    'id' => $cuota->id,
                    'credito_id' => $cuota->credito_id,
                    'cliente_nombre' => $cuota->credito->cliente->tercero->nombre,
                    'numero_cuota' => $cuota->numero_cuota,
                    'saldo' => $cuota->valor_total_cuota->restar($cuota->valor_pagado),
                    'valor_total' => $cuota->valor_total_cuota,
                    'valor_pagado' => $cuota->valor_pagado,
                    'valor_capital' => $cuota->valor_capital,
                    'valor_interes' => $cuota->valor_interes,
                    'valor_otros_gastos' => $cuota->valor_otros_gastos,
                    'fecha_vencimiento' => $cuota->fecha_vencimiento->toDateString(),
                    'estado' => $cuota->estado,
                    'color' => $this->colorPara($cuota->estado),
                ])
                ->values()
                ->all(),
        ];
    }

    private function colorPara(EstadoCuota $estado): string
    {
        return match ($estado) {
            EstadoCuota::Pagada => 'success',
            EstadoCuota::Vencida => 'danger',
            EstadoCuota::Pendiente => 'warning',
        };
    }
}
