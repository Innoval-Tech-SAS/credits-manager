<?php

namespace App\Dashboard\Services;

use App\Cartera\Constants\CarteraConstants;
use App\Core\Dinero\Dinero;
use App\Creditos\Enums\EstadoCredito;
use App\Creditos\Services\CuotaService;
use App\Dashboard\Repositories\DashboardRepository;
use App\Usuarios\Models\Usuario;
use Carbon\CarbonImmutable;

/**
 * Sin tabla propia. Arma el dashboard con los agregados de `DashboardRepository`. Cada bloque solo se consulta si el
 * usuario tiene el permiso de lectura de su módulo (créditos activos con `creditos`, clientes activos con `clientes`,
 * cuotas del día con `cartera`, gastos con `gastos.listar_gastos`); el resto no llega a la base y sale en `null`.
 *
 * La ventana histórica son 13 meses: los 12 que se muestran más el año anterior del mes elegido, que sirve de
 * comparación interanual sin una segunda consulta.
 */
class DashboardService
{
    private const MESES_VISIBLES = 12;

    public function __construct(
        protected CuotaService $cuotaService,
        protected DashboardRepository $dashboard,
    ) {}

    public function resumen(Usuario $usuario, int $empresaId, ?int $asesorId, ?int $clienteId, ?EstadoCredito $estado, CarbonImmutable $periodo): array
    {
        $this->cuotaService->actualizarVencidas();

        $hoy = CarbonImmutable::now()->toDateString();
        $meses = $this->meses($periodo);
        $desde = $periodo->subMonths(self::MESES_VISIBLES)->startOfMonth()->toDateString();
        $hasta = $periodo->addMonth()->startOfMonth()->toDateString();

        $verColocacion = $this->puede($usuario, 'creditos');
        $verRecaudo = $this->puede($usuario, 'pagos');
        $verCartera = $this->puede($usuario, 'cartera');
        $verGastos = $this->puede($usuario, 'gastos', 'listar_gastos');
        $verClientes = $this->puede($usuario, 'clientes');

        $colocacion = $verColocacion ? $this->dashboard->colocacionPorMes($empresaId, $asesorId, $clienteId, $estado, $desde, $hasta) : null;
        $recaudo = $verRecaudo ? $this->dashboard->recaudoPorMes($empresaId, $asesorId, $clienteId, $estado, $desde, $hasta) : null;
        $gastos = $verGastos ? $this->dashboard->gastosPorMes($empresaId, $desde, $hasta) : null;

        return [
            'periodo' => [
                'mes' => $periodo->format('Y-m'),
                'inicio' => $periodo->toDateString(),
                'fin' => $periodo->endOfMonth()->toDateString(),
            ],
            'historico' => [
                'meses' => array_slice($meses, 1),
                'colocacion' => $colocacion !== null ? $this->serie($this->centavosDe($colocacion), $meses) : null,
                'recaudo' => $recaudo !== null ? $this->serie($recaudo, $meses) : null,
                'gastos' => $gastos !== null ? $this->serie($gastos, $meses) : null,
            ],
            'metricas' => [
                'clientes_activos' => $verClientes ? $this->dashboard->contarClientesActivos($empresaId, $asesorId) : null,
                'creditos_activos' => $verColocacion ? $this->dashboard->contarCreditosActivos($empresaId, $asesorId, $clienteId, $estado) : null,
                'colocacion' => $colocacion !== null ? [
                    ...$this->bloque($this->centavosDe($colocacion), $meses),
                    'cantidad' => $colocacion[$meses[12]]['cantidad'] ?? 0,
                ] : null,
                'recaudo' => $recaudo !== null ? $this->bloque($recaudo, $meses) : null,
                'gastos' => $gastos !== null ? $this->bloque($gastos, $meses) : null,
                'cartera' => $verCartera ? $this->cartera($empresaId, $asesorId, $clienteId, $estado, $hoy) : null,
            ],
            'pagos_hoy' => $verCartera ? $this->mapearPagosDelDia($this->dashboard->pagosDelDia($empresaId, $asesorId, $clienteId, $estado, $hoy)) : null,
        ];
    }

    /** El superadmin, que no tiene rol, ve todos los bloques de la empresa que eligió. */
    private function puede(Usuario $usuario, string $modulo, string $accion = 'listar'): bool
    {
        return $usuario->es_superadmin || $usuario->tienePermiso($modulo, $accion);
    }

    /** 13 meses `YYYY-MM` terminando en el período elegido. */
    private function meses(CarbonImmutable $periodo): array
    {
        return array_map(
            fn (int $desplazamiento) => $periodo->subMonths(self::MESES_VISIBLES - $desplazamiento)->format('Y-m'),
            range(0, self::MESES_VISIBLES),
        );
    }

    /** @return array<string, int> */
    private function centavosDe(array $colocacion): array
    {
        return array_map(fn (array $fila) => $fila['valor'], $colocacion);
    }

    /**
     * Serie de los 12 meses visibles como cadenas decimales. El valor del mes elegido es el último.
     *
     * @param  array<string, int>  $centavosPorMes
     * @return string[]
     */
    private function serie(array $centavosPorMes, array $meses): array
    {
        return array_map(
            fn (string $mes) => Dinero::desdeCentavos((int) ($centavosPorMes[$mes] ?? 0))->aDecimalString(),
            array_slice($meses, 1),
        );
    }

    /**
     * Valor del período con su comparación vs. mes anterior y vs. mismo mes del año anterior.
     * La variación es un porcentaje (no dinero), redondeado a un decimal; `null` si no hay base para comparar.
     *
     * @param  array<string, int>  $centavosPorMes
     */
    private function bloque(array $centavosPorMes, array $meses): array
    {
        $actual = (int) ($centavosPorMes[$meses[12]] ?? 0);
        $mesAnterior = (int) ($centavosPorMes[$meses[11]] ?? 0);
        $anioAnterior = (int) ($centavosPorMes[$meses[0]] ?? 0);

        return [
            'valor' => Dinero::desdeCentavos($actual)->aDecimalString(),
            'mes_anterior' => $this->comparacion($actual, $mesAnterior),
            'anio_anterior' => $this->comparacion($actual, $anioAnterior),
        ];
    }

    private function comparacion(int $actual, int $referencia): array
    {
        return [
            'valor' => Dinero::desdeCentavos($referencia)->aDecimalString(),
            'variacion' => $referencia > 0 ? round(($actual - $referencia) / $referencia * 100, 1) : null,
        ];
    }

    private function cartera(int $empresaId, ?int $asesorId, ?int $clienteId, ?EstadoCredito $estado, string $hoy): array
    {
        $cartera = $this->dashboard->carteraActual($empresaId, $asesorId, $clienteId, $estado, $hoy);
        $fechaHoy = CarbonImmutable::parse($hoy);

        $porRango = $this->dashboard->moraPorRango($empresaId, $asesorId, $clienteId, $estado, $hoy, [
            '1_30' => $fechaHoy->subDays(30)->toDateString(),
            '31_60' => $fechaHoy->subDays(60)->toDateString(),
            '61_90' => $fechaHoy->subDays(90)->toDateString(),
        ]);

        $rangos = [];
        $creditosEnMora = 0;
        $saldoEnMora = 0;

        foreach (array_keys(CarteraConstants::RANGOS_MORA) as $clave) {
            $fila = $porRango[$clave] ?? ['creditos' => 0, 'saldo' => 0];
            $creditosEnMora += $fila['creditos'];
            $saldoEnMora += $fila['saldo'];

            $rangos[] = [
                'clave' => $clave,
                'creditos' => $fila['creditos'],
                'saldo' => Dinero::desdeCentavos($fila['saldo'])->aDecimalString(),
            ];
        }

        return [
            'pendiente' => Dinero::desdeCentavos($cartera['pendiente'])->aDecimalString(),
            'al_dia' => Dinero::desdeCentavos($cartera['al_dia'])->aDecimalString(),
            'vencida' => Dinero::desdeCentavos($cartera['vencida'])->aDecimalString(),
            'mora' => [
                'creditos' => $creditosEnMora,
                'saldo' => Dinero::desdeCentavos($saldoEnMora)->aDecimalString(),
                'promedio' => $creditosEnMora > 0 ? Dinero::desdeCentavos($saldoEnMora)->dividirEntre($creditosEnMora)->aDecimalString() : '0.00',
                'rangos' => $rangos,
            ],
        ];
    }

    private function mapearPagosDelDia(array $cuotas): array
    {
        return array_map(function (object $cuota) {
            $total = Dinero::desdeCentavos((int) $cuota->valor)->sumar(Dinero::desdeCentavos((int) $cuota->valor_otros_gastos));

            return [
                'cuota_id' => (int) $cuota->cuota_id,
                'credito_id' => (int) $cuota->credito_id,
                'cliente_nombre' => $cuota->cliente_nombre,
                'numero_cuota' => (int) $cuota->numero_cuota,
                'valor' => $total->aDecimalString(),
                'pendiente' => $total->restar(Dinero::desdeCentavos((int) $cuota->valor_pagado))->noNegativo()->aDecimalString(),
            ];
        }, $cuotas);
    }
}
