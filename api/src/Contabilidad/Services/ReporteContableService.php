<?php

namespace App\Contabilidad\Services;

use App\Contabilidad\Enums\NaturalezaCuenta;
use App\Contabilidad\Enums\TipoCuenta;
use App\Contabilidad\Exportaciones\FuenteBalancePrueba;
use App\Contabilidad\Exportaciones\FuenteEstadosFinancieros;
use App\Contabilidad\Exportaciones\FuenteLibroDiario;
use App\Contabilidad\Exportaciones\FuenteLibroMayor;
use App\Contabilidad\Models\PlanCuenta;
use App\Contabilidad\Repositories\AsientoContableRepository;
use App\Contabilidad\Repositories\PlanCuentaRepository;
use App\Core\Dinero\Dinero;
use App\Exportacion\Enums\FormatoExportacion;
use App\Exportacion\Models\Exportacion;
use App\Exportacion\Services\ExportacionService;
use App\Usuarios\Models\Usuario;

class ReporteContableService
{
    public function __construct(
        protected AsientoContableRepository $asientos,
        protected PlanCuentaRepository $cuentas,
        protected ExportacionService $exportaciones,
    ) {}

    public function libroDiario(int $empresaId, string $desde, string $hasta): array
    {
        $movimientos = $this->asientos->movimientosLibroDiario($empresaId, $desde, $hasta);

        return [
            'movimientos' => $movimientos,
            'total_debito' => Dinero::sumarVarios(...$movimientos->pluck('debito')->all()),
            'total_credito' => Dinero::sumarVarios(...$movimientos->pluck('credito')->all()),
        ];
    }

    public function libroMayor(int $empresaId, int $cuentaId, string $desde, string $hasta): array
    {
        $cuenta = $this->cuentas->buscarDeEmpresaOFallar($empresaId, $cuentaId);

        return [
            'cuenta' => $cuenta,
            'saldo_inicial' => $this->saldoInicialDeCuenta($empresaId, $cuenta, $desde),
            'movimientos' => $this->asientos->movimientosLibroMayor($empresaId, $cuentaId, $desde, $hasta),
        ];
    }

    /** Saldo de la cuenta antes de `$desde`, con el signo de su naturaleza. */
    public function saldoInicialDeCuenta(int $empresaId, PlanCuenta $cuenta, string $desde): Dinero
    {
        $antes = $this->asientos->saldoAntesDeFecha($empresaId, $cuenta->id, $desde);
        $debitos = Dinero::desdeCentavos($antes['debitos']);
        $creditos = Dinero::desdeCentavos($antes['creditos']);

        return $cuenta->naturaleza === NaturalezaCuenta::Debito
            ? $debitos->restar($creditos)
            : $creditos->restar($debitos);
    }

    public function balancePrueba(int $empresaId, string $hasta): array
    {
        $saldos = $this->cuentas->saldosParaBalancePrueba($empresaId, $hasta)->map(function ($fila) {
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

    public function estadosFinancieros(int $empresaId, string $desde, string $hasta): array
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

        $activos = $this->cuentas->saldosPorTipo($empresaId, [TipoCuenta::Activo->value], null, $hasta);
        $pasivos = $this->cuentas->saldosPorTipo($empresaId, [TipoCuenta::Pasivo->value], null, $hasta);
        $patrimonio = $this->cuentas->saldosPorTipo($empresaId, [TipoCuenta::Patrimonio->value], null, $hasta);
        $ingresos = $this->cuentas->saldosPorTipo($empresaId, [TipoCuenta::Ingreso->value], $desde, $hasta);
        $gastosCostos = $this->cuentas->saldosPorTipo($empresaId, [TipoCuenta::Gasto->value, TipoCuenta::Costo->value], $desde, $hasta);

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

    public function solicitarExportacionLibroDiario(Usuario $usuario, string $desde, string $hasta, FormatoExportacion $formato): Exportacion
    {
        return $this->exportaciones->solicitar(new FuenteLibroDiario((int) $usuario->empresa_id, $desde, $hasta), $formato, $usuario);
    }

    public function solicitarExportacionLibroMayor(Usuario $usuario, int $cuentaId, string $desde, string $hasta, FormatoExportacion $formato): Exportacion
    {
        return $this->exportaciones->solicitar(new FuenteLibroMayor((int) $usuario->empresa_id, $cuentaId, $desde, $hasta), $formato, $usuario);
    }

    public function solicitarExportacionBalancePrueba(Usuario $usuario, string $hasta, FormatoExportacion $formato): Exportacion
    {
        return $this->exportaciones->solicitar(new FuenteBalancePrueba((int) $usuario->empresa_id, $hasta), $formato, $usuario);
    }

    public function solicitarExportacionEstadosFinancieros(Usuario $usuario, string $desde, string $hasta, FormatoExportacion $formato): Exportacion
    {
        return $this->exportaciones->solicitar(new FuenteEstadosFinancieros((int) $usuario->empresa_id, $desde, $hasta), $formato, $usuario);
    }
}
