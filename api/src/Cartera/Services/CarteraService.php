<?php

namespace App\Cartera\Services;

use App\Core\Dinero\Dinero;
use App\Creditos\Repositories\CreditoRepository;
use App\Creditos\Repositories\CuotaRepository;
use App\Creditos\Services\CuotaService;
use App\Empleadores\Repositories\EmpleadorRepository;

/**
 * Sin tabla propia — solo lectura sobre créditos/cuotas activos. Reusa
 * `CuotaService` (mismo dueño de la lógica de mora que Créditos/Calendario).
 */
class CarteraService
{
    public function __construct(
        protected CuotaService $cuotaService,
        protected CreditoRepository $creditos,
        protected EmpleadorRepository $empleadores,
        protected CuotaRepository $cuotas,
    ) {}

    public function resumen(): array
    {
        $this->cuotaService->actualizarVencidas();

        $creditos = $this->creditos->activosConCliente();
        $resumenPorCredito = $this->cuotas->resumenPorCreditos($creditos->pluck('id')->all());

        $alDia = [];
        $enMora = [];

        foreach ($creditos as $credito) {
            $fila = $resumenPorCredito[$credito->id] ?? ['saldo' => 0, 'vencidas' => 0, 'primera_fecha_vencida' => null];
            $saldo = Dinero::desdeCentavos($fila['saldo']);

            if ((int) $fila['vencidas'] > 0) {
                $diasMora = $this->cuotaService->diasMoraDesdeFecha($fila['primera_fecha_vencida']);
                $enMora[] = ['credito' => $credito, 'saldo' => $saldo, 'dias_mora' => $diasMora];
            } else {
                $alDia[] = ['credito' => $credito, 'saldo' => $saldo];
            }
        }

        return [
            'al_dia' => $alDia,
            'en_mora' => $enMora,
            'totales' => [
                'al_dia' => $this->totalConPromedio($alDia),
                'en_mora' => $this->totalConPromedio($enMora),
            ],
            'envejecimiento_mora' => $this->envejecimientoMora($enMora),
        ];
    }

    private function totalConPromedio(array $filas): array
    {
        $count = count($filas);
        $saldo = Dinero::sumarVarios(...array_column($filas, 'saldo'));

        return [
            'count' => $count,
            'saldo' => $saldo,
            'promedio' => $count > 0 ? $saldo->dividirEntre($count) : Dinero::cero(),
        ];
    }

    /**
     * Agrupa los créditos en mora en los rangos de antigüedad estándar de cobranza
     * (1-30 / 31-60 / 61+ días), a partir del `dias_mora` que ya calcula `resumen()`.
     */
    private function envejecimientoMora(array $enMora): array
    {
        $rangos = [
            '1_30' => ['count' => 0, 'saldo' => Dinero::cero()],
            '31_60' => ['count' => 0, 'saldo' => Dinero::cero()],
            '61_mas' => ['count' => 0, 'saldo' => Dinero::cero()],
        ];

        foreach ($enMora as $fila) {
            $clave = match (true) {
                $fila['dias_mora'] <= 30 => '1_30',
                $fila['dias_mora'] <= 60 => '31_60',
                default => '61_mas',
            };

            $rangos[$clave]['count']++;
            $rangos[$clave]['saldo'] = $rangos[$clave]['saldo']->sumar($fila['saldo']);
        }

        return $rangos;
    }

    public function reporteEmpleador(?int $empleadorId): array
    {
        $this->cuotaService->actualizarVencidas();

        $empleadores = $this->empleadores->conClientes();
        $empleadorSeleccionado = $empleadorId ?? $empleadores->first()?->id;

        $meses = [];
        $filas = [];

        if ($empleadorSeleccionado) {
            foreach ($this->cuotas->porEmpleador($empleadorSeleccionado) as $cuota) {
                $periodo = $cuota->fecha_vencimiento->format('Y-m');

                if (! in_array($periodo, $meses, true)) {
                    $meses[] = $periodo;
                }

                $clienteId = $cuota->credito->cliente_id;
                $filas[$clienteId] ??= [
                    'cliente_id' => $clienteId,
                    'cliente_nombre' => $cuota->credito->cliente->tercero->nombre,
                    'valor_cuota' => $cuota->credito->valor_cuota,
                    'cuotas' => [],
                ];
                $filas[$clienteId]['cuotas'][$periodo] = ['valor' => $cuota->valor, 'estado' => $cuota->estado];
            }

            sort($meses);
        }

        $totalesPorMes = array_fill_keys($meses, Dinero::cero());
        foreach ($filas as $fila) {
            foreach ($fila['cuotas'] as $periodo => $cuota) {
                $totalesPorMes[$periodo] = $totalesPorMes[$periodo]->sumar($cuota['valor']);
            }
        }

        return [
            'empleadores' => $empleadores,
            'empleador_seleccionado' => $empleadorSeleccionado,
            'meses' => $meses,
            'filas' => array_values($filas),
            'totales_por_mes' => $totalesPorMes,
        ];
    }
}
