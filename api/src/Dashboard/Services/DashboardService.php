<?php

namespace App\Dashboard\Services;

use App\Clientes\Repositories\ClienteRepository;
use App\Core\Dinero\Dinero;
use App\Creditos\Models\Cuota;
use App\Creditos\Repositories\CreditoRepository;
use App\Creditos\Repositories\CuotaRepository;
use App\Creditos\Services\CuotaService;
use Illuminate\Support\Collection;

/**
 * Sin tabla propia — replica `index.php` del legado: 4 métricas + cuotas que
 * vencen hoy. Reusa `CuotaService` (mismo dueño de mora/saldo que
 * Créditos/Cartera/Calendario).
 */
class DashboardService
{
    public function __construct(
        protected CuotaService $cuotaService,
        protected ClienteRepository $clientes,
        protected CreditoRepository $creditos,
        protected CuotaRepository $cuotas,
    ) {}

    public function resumen(): array
    {
        $this->cuotaService->actualizarVencidas();

        $creditosActivos = $this->creditos->activosConCliente();
        $resumenPorCredito = $this->cuotas->resumenPorCreditos($creditosActivos->pluck('id')->all());

        $carteraPendiente = Dinero::cero();
        $creditosEnMora = 0;

        foreach ($creditosActivos as $credito) {
            $fila = $resumenPorCredito[$credito->id] ?? ['saldo' => 0, 'vencidas' => 0];
            $carteraPendiente = $carteraPendiente->sumar(Dinero::desdeCentavos($fila['saldo']));

            if ((int) $fila['vencidas'] > 0) {
                $creditosEnMora++;
            }
        }

        return [
            'clientes_activos' => $this->clientes->contarActivos(),
            'creditos_activos' => $this->creditos->contarActivos(),
            'cartera_pendiente' => $carteraPendiente,
            'creditos_en_mora' => $creditosEnMora,
            'pagos_hoy' => $this->mapearPagosHoy($this->cuotas->venceHoyDeCreditosActivos()),
        ];
    }

    private function mapearPagosHoy(Collection $cuotas): array
    {
        return $cuotas->map(fn (Cuota $cuota) => [
            'cuota_id' => $cuota->id,
            'credito_id' => $cuota->credito_id,
            'cliente_nombre' => $cuota->credito->cliente->tercero->nombre,
            'numero_cuota' => $cuota->numero_cuota,
            'valor' => $cuota->valor,
            'pendiente' => $cuota->valor->restar($cuota->valor_pagado),
        ])->all();
    }
}
