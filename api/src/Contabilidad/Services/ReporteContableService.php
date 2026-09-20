<?php

namespace App\Contabilidad\Services;

use App\Contabilidad\Enums\NaturalezaCuenta;
use App\Contabilidad\Enums\TipoCuenta;
use App\Contabilidad\Repositories\AsientoContableRepository;
use App\Contabilidad\Repositories\PlanCuentaRepository;
use App\Core\Dinero\Dinero;

class ReporteContableService
{
    public function __construct(
        protected AsientoContableRepository $asientos,
        protected PlanCuentaRepository $cuentas,
    ) {}

    public function libroDiario(string $desde, string $hasta): array
    {
        $movimientos = $this->asientos->movimientosLibroDiario($desde, $hasta);

        return [
            'movimientos' => $movimientos,
            'total_debito' => Dinero::sumarVarios(...$movimientos->pluck('debito')->all()),
            'total_credito' => Dinero::sumarVarios(...$movimientos->pluck('credito')->all()),
        ];
    }

    public function libroMayor(int $cuentaId, string $desde, string $hasta): array
    {
        $cuenta = $this->cuentas->buscarPorIdOFallar($cuentaId);
        $antes = $this->asientos->saldoAntesDeFecha($cuentaId, $desde);
        $debitos = Dinero::desdeCentavos($antes['debitos']);
        $creditos = Dinero::desdeCentavos($antes['creditos']);
        $saldoInicial = $cuenta->naturaleza === NaturalezaCuenta::Debito
            ? $debitos->restar($creditos)
            : $creditos->restar($debitos);

        return [
            'cuenta' => $cuenta,
            'saldo_inicial' => $saldoInicial,
            'movimientos' => $this->asientos->movimientosLibroMayor($cuentaId, $desde, $hasta),
        ];
    }

    public function balancePrueba(string $hasta): array
    {
        $saldos = $this->cuentas->saldosParaBalancePrueba($hasta)->map(function ($fila) {
            $debitos = Dinero::desdeCentavos((int) $fila->debitos);
            $creditos = Dinero::desdeCentavos((int) $fila->creditos);
            $saldo = $fila->naturaleza === NaturalezaCuenta::Debito ? $debitos->restar($creditos) : $creditos->restar($debitos);

            return [
                'codigo' => $fila->codigo,
                'nombre' => $fila->nombre,
                'debitos' => $debitos,
                'creditos' => $creditos,
                'saldo' => $saldo,
            ];
        });

        return [
            'saldos' => $saldos,
            'total_debito' => Dinero::sumarVarios(...$saldos->pluck('debitos')->all()),
            'total_credito' => Dinero::sumarVarios(...$saldos->pluck('creditos')->all()),
        ];
    }

    public function estadosFinancieros(string $desde, string $hasta): array
    {
        $neto = function ($fila) {
            $d = Dinero::desdeCentavos((int) $fila->d);
            $c = Dinero::desdeCentavos((int) $fila->c);

            return $fila->naturaleza === NaturalezaCuenta::Debito ? $d->restar($c) : $c->restar($d);
        };

        $filaDto = fn ($fila, Dinero $valor) => [
            'codigo' => $fila->codigo,
            'nombre' => $fila->nombre,
            'valor' => $valor,
        ];
        $conValores = fn ($coleccion) => $coleccion->map(fn ($fila) => $filaDto($fila, $neto($fila)));

        $activos = $this->cuentas->saldosPorTipo([TipoCuenta::Activo->value], null, $hasta);
        $pasivos = $this->cuentas->saldosPorTipo([TipoCuenta::Pasivo->value], null, $hasta);
        $patrimonio = $this->cuentas->saldosPorTipo([TipoCuenta::Patrimonio->value], null, $hasta);
        $ingresos = $this->cuentas->saldosPorTipo([TipoCuenta::Ingreso->value], $desde, $hasta);
        $gastosCostos = $this->cuentas->saldosPorTipo([TipoCuenta::Gasto->value, TipoCuenta::Costo->value], $desde, $hasta);

        $totalActivos = Dinero::sumarVarios(...$activos->map($neto)->all());
        $totalPasivos = Dinero::sumarVarios(...$pasivos->map($neto)->all());
        $totalPatrimonio = Dinero::sumarVarios(...$patrimonio->map($neto)->all());
        $totalIngresos = Dinero::sumarVarios(...$ingresos->map($neto)->all());
        $totalGastos = Dinero::sumarVarios(...$gastosCostos->map($neto)->all());
        $utilidad = $totalIngresos->restar($totalGastos);

        return [
            'estado_resultados' => [
                'ingresos' => $conValores($ingresos),
                'gastos_costos' => $conValores($gastosCostos),
                'total_ingresos' => $totalIngresos,
                'total_gastos' => $totalGastos,
                'utilidad' => $utilidad,
            ],
            'situacion_financiera' => [
                'activos' => $conValores($activos),
                'pasivos' => $conValores($pasivos),
                'patrimonio' => $conValores($patrimonio),
                'total_activos' => $totalActivos,
                'total_pasivos' => $totalPasivos,
                'total_patrimonio' => $totalPatrimonio,
                'utilidad' => $utilidad,
                'cuadra' => $totalActivos->igualA($totalPasivos->sumar($totalPatrimonio)->sumar($utilidad)),
            ],
        ];
    }
}
