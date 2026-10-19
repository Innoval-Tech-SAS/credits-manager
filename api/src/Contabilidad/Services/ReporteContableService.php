<?php

namespace App\Contabilidad\Services;

use App\Contabilidad\Constants\ContabilidadConstants;
use App\Contabilidad\Enums\NaturalezaCuenta;
use App\Contabilidad\Enums\TipoCuenta;
use App\Contabilidad\Exportaciones\FuenteBalancePrueba;
use App\Contabilidad\Exportaciones\FuenteEstadosFinancieros;
use App\Contabilidad\Exportaciones\FuenteLibroDiario;
use App\Contabilidad\Exportaciones\FuenteLibroMayor;
use App\Contabilidad\Models\PlanCuenta;
use App\Contabilidad\Repositories\AsientoContableRepository;
use App\Contabilidad\Repositories\PlanCuentaRepository;
use App\Contabilidad\Support\NumeroComprobante;
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
        $movimientos = $this->asientos->movimientosLibroDiario($empresaId, $desde, $hasta)
            ->each(fn ($fila) => $fila->numero = NumeroComprobante::formatear($fila->tipo, $fila->ciclo_numeracion, $fila->consecutivo));

        return [
            'movimientos' => $movimientos,
            'total_debito' => Dinero::sumarVarios(...$movimientos->pluck('debito')->all()),
            'total_credito' => Dinero::sumarVarios(...$movimientos->pluck('credito')->all()),
        ];
    }

    public function libroMayor(int $empresaId, int $cuentaId, string $desde, string $hasta): array
    {
        $cuenta = $this->cuentas->buscarDeEmpresaOFallar($empresaId, $cuentaId);
        $movimientos = $this->asientos->movimientosLibroMayor($empresaId, $cuentaId, $desde, $hasta)
            ->each(fn ($fila) => $fila->numero = NumeroComprobante::formatear($fila->tipo, $fila->ciclo_numeracion, $fila->consecutivo));

        return [
            'cuenta' => $cuenta,
            'saldo_inicial' => $this->saldoInicialDeCuenta($empresaId, $cuenta, $desde),
            'movimientos' => $movimientos,
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
        /** El signo sale del tipo de cuenta en el estado financiero, no de la naturaleza propia de la cuenta:
         * así una cuenta correctora (p. ej. 1399, naturaleza crédito dentro de Activo) resta del total del
         * grupo en vez de sumarlo. */
        $neto = function ($fila, bool $debitoAumenta) {
            $d = Dinero::desdeCentavos((int) $fila->d);
            $c = Dinero::desdeCentavos((int) $fila->c);

            return $debitoAumenta ? $d->restar($c) : $c->restar($d);
        };

        $filaDto = fn ($fila, Dinero $valor) => [
            'codigo' => $fila->codigo,
            'nombre' => $fila->nombre,
            'valor' => $valor,
        ];
        $conValores = fn ($coleccion, bool $debitoAumenta) => $coleccion->map(fn ($fila) => $filaDto($fila, $neto($fila, $debitoAumenta)));
        $totalDe = fn ($coleccion, bool $debitoAumenta) => Dinero::sumarVarios(...$coleccion->map(fn ($fila) => $neto($fila, $debitoAumenta))->all());

        $activos = $this->cuentas->saldosPorTipo($empresaId, [TipoCuenta::Activo->value], null, $hasta);
        $pasivos = $this->cuentas->saldosPorTipo($empresaId, [TipoCuenta::Pasivo->value], null, $hasta);
        $patrimonio = $this->cuentas->saldosPorTipo($empresaId, [TipoCuenta::Patrimonio->value], null, $hasta);
        $ingresos = $this->cuentas->saldosPorTipo($empresaId, [TipoCuenta::Ingreso->value], $desde, $hasta);
        $gastosCostos = $this->cuentas->saldosPorTipo($empresaId, [TipoCuenta::Gasto->value, TipoCuenta::Costo->value], $desde, $hasta);

        $totalActivos = $totalDe($activos, true);
        $totalPasivos = $totalDe($pasivos, false);
        $totalPatrimonio = $totalDe($patrimonio, false);
        $totalIngresos = $totalDe($ingresos, false);
        $totalGastos = $totalDe($gastosCostos, true);
        $utilidad = $totalIngresos->restar($totalGastos);

        return [
            'estado_resultados' => [
                'ingresos' => $conValores($ingresos, false),
                'gastos_costos' => $conValores($gastosCostos, true),
                'total_ingresos' => $totalIngresos,
                'total_gastos' => $totalGastos,
                'utilidad' => $utilidad,
            ],
            'situacion_financiera' => [
                'activos' => $conValores($activos, true),
                'pasivos' => $conValores($pasivos, false),
                'patrimonio' => $conValores($patrimonio, false),
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

    /** Una cuenta de caja y bancos (clase 11) se exporta como `contabilidad.caja_bancos`: la autoriza también ese permiso. */
    public function solicitarExportacionLibroMayor(Usuario $usuario, int $cuentaId, string $desde, string $hasta, FormatoExportacion $formato): Exportacion
    {
        $cuenta = $this->cuentas->buscarDeEmpresaOFallar((int) $usuario->empresa_id, $cuentaId);
        $esCajaBancos = str_starts_with($cuenta->codigo, ContabilidadConstants::PREFIJO_CUENTAS_CAJA_BANCOS);

        return $this->exportaciones->solicitar(new FuenteLibroMayor((int) $usuario->empresa_id, $cuentaId, $desde, $hasta, $esCajaBancos), $formato, $usuario);
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
