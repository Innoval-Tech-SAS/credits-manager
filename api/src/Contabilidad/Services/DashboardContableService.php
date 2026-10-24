<?php

namespace App\Contabilidad\Services;

use App\Contabilidad\Constants\ContabilidadConstants;
use App\Contabilidad\Enums\EstadoComprobante;
use App\Contabilidad\Enums\EventoContable;
use App\Contabilidad\Enums\NaturalezaCuenta;
use App\Contabilidad\Enums\TipoCuenta;
use App\Contabilidad\Repositories\AsientoContableRepository;
use App\Contabilidad\Repositories\CentroCostoRepository;
use App\Contabilidad\Repositories\ComprobanteRepository;
use App\Contabilidad\Repositories\PeriodoContableRepository;
use App\Contabilidad\Repositories\PlanCuentaRepository;
use App\Contabilidad\Repositories\ReglaContabilizacionRepository;
use App\Contabilidad\Repositories\SolicitudReaperturaPeriodoRepository;
use App\Core\Dinero\Dinero;
use App\Creditos\Repositories\CreditoRepository;
use App\Creditos\Repositories\CuotaRepository;
use App\Monitoreo\Repositories\EjecucionTareaProgramadaRepository;
use Illuminate\Support\Carbon;

/**
 * Resumen ejecutivo del dashboard contable: compone `ReporteContableService` y `ControlesCierreService` en vez de
 * recalcular sus totales. Único número que NO sale de los libros: el lado "operativo" del control de cartera
 * (6), que compara a propósito el saldo de los créditos vigentes contra el saldo contable de la 1305 — esa
 * diferencia es justo lo que el control debe mostrar.
 */
class DashboardContableService
{
    public function __construct(
        protected ReporteContableService $reportes,
        protected ControlesCierreService $controlesCierre,
        protected PlanCuentaRepository $cuentas,
        protected AsientoContableRepository $asientos,
        protected PeriodoContableRepository $periodos,
        protected SolicitudReaperturaPeriodoRepository $solicitudesReapertura,
        protected ComprobanteRepository $comprobantes,
        protected ReglaContabilizacionRepository $reglas,
        protected CentroCostoRepository $centrosCosto,
        protected CreditoRepository $creditos,
        protected CuotaRepository $cuotas,
        protected EjecucionTareaProgramadaRepository $ejecucionesTareas,
    ) {}

    /** @return array{total_activos: Dinero, total_pasivos: Dinero, total_patrimonio: Dinero, utilidad: Dinero, cuadra: bool, composicion_activo: array, composicion_pasivo: array, composicion_patrimonio: array, cartera_neta: array} */
    public function situacion(int $empresaId, string $hasta): array
    {
        $situacion = $this->reportes->estadosFinancieros($empresaId, $this->inicioDeAnio($hasta), $hasta)['situacion_financiera'];

        return [
            'total_activos' => $situacion['total_activos'],
            'total_pasivos' => $situacion['total_pasivos'],
            'total_patrimonio' => $situacion['total_patrimonio'],
            'utilidad' => $situacion['utilidad'],
            'cuadra' => $situacion['cuadra'],
            'composicion_activo' => $this->composicionPorGrupo($situacion['activos']),
            'composicion_pasivo' => $this->composicionPorGrupo($situacion['pasivos']),
            'composicion_patrimonio' => $this->composicionPorGrupo($situacion['patrimonio']),
            'cartera_neta' => $this->carteraNetaContable($situacion['activos']),
        ];
    }

    public function resultados(int $empresaId, string $desde, string $hasta): array
    {
        $estadoResultados = $this->reportes->estadosFinancieros($empresaId, $desde, $hasta)['estado_resultados'];
        $anioAnteriorDesde = Carbon::parse($desde)->subYear()->toDateString();
        $anioAnteriorHasta = Carbon::parse($hasta)->subYear()->toDateString();
        $utilidadAnioAnterior = $this->reportes->estadosFinancieros($empresaId, $anioAnteriorDesde, $anioAnteriorHasta)['estado_resultados']['utilidad'];
        $utilidadAcumuladaAnio = $this->reportes->estadosFinancieros($empresaId, $this->inicioDeAnio($hasta), $hasta)['estado_resultados']['utilidad'];

        return [
            'ingresos' => $estadoResultados['ingresos'],
            'gastos_por_grupo' => $this->composicionPorGrupo($estadoResultados['gastos_costos']),
            'gastos_por_centro_costo' => $this->gastosPorCentroCosto($empresaId, $desde, $hasta),
            'total_ingresos' => $estadoResultados['total_ingresos'],
            'total_gastos' => $estadoResultados['total_gastos'],
            'utilidad_periodo' => $estadoResultados['utilidad'],
            'utilidad_acumulada_anio' => $utilidadAcumuladaAnio,
            'utilidad_mismo_periodo_anio_anterior' => $utilidadAnioAnterior,
            'serie_mensual' => $this->serieMensual($empresaId, $hasta),
        ];
    }

    public function cierre(int $empresaId, string $hasta): array
    {
        $controles = $this->controlesCierre->ejecutar($empresaId, $hasta);

        return [
            'periodo' => $this->periodoAbierto($hasta),
            'solicitudes_reapertura_pendientes' => $this->solicitudesPendientes(),
            'controles_cierre' => [
                'cuentas_por_cobrar' => ['cuadra' => $controles['cuentas_por_cobrar']['cuadra'], 'diferencia' => $controles['cuentas_por_cobrar']['diferencia']],
                'cuentas_por_pagar' => ['cuadra' => $controles['cuentas_por_pagar']['cuadra'], 'diferencia' => $controles['cuentas_por_pagar']['diferencia']],
                'saldos_contrarios' => $controles['saldos_contrarios']['contador'],
                'documentos_inconsistentes' => $controles['documentos_inconsistentes']['contador'],
                'conciliacion_bancaria' => $controles['conciliacion_bancaria']['contador'],
            ],
            'cartera_operativa_vs_contable' => $this->carteraOperativaVsContable($empresaId, $hasta),
            'comprobantes_pendientes_revision' => $this->comprobantes->contarPorEstado(EstadoComprobante::Borrador),
            'devengo_intereses' => $this->devengoIntereses(),
            'eventos_sin_regla' => $this->eventosSinRegla(),
        ];
    }

    private function inicioDeAnio(string $fecha): string
    {
        return substr($fecha, 0, 4).'-01-01';
    }

    /** Agrupa filas de cuenta (`['codigo','nombre','valor' => Dinero]`) por los primeros 2 dígitos del código. */
    private function composicionPorGrupo(iterable $filas): array
    {
        $valoresPorGrupo = [];

        foreach ($filas as $fila) {
            $valoresPorGrupo[substr($fila['codigo'], 0, 2)][] = $fila['valor'];
        }

        $nombresDeGrupo = $this->cuentas->porCodigos(array_keys($valoresPorGrupo))->pluck('nombre', 'codigo');

        $resultado = array_map(fn (string $grupo, array $valores) => [
            'codigo' => $grupo,
            'nombre' => $nombresDeGrupo[$grupo] ?? $grupo,
            'valor' => Dinero::sumarVarios(...$valores),
        ], array_keys($valoresPorGrupo), $valoresPorGrupo);

        usort($resultado, fn (array $a, array $b) => strcmp($a['codigo'], $b['codigo']));

        return $resultado;
    }

    /** Las filas de `activos` ya traen el signo de la ecuación contable: sumar 1305 y 1399 directo da la cartera neta. */
    private function carteraNetaContable(iterable $filasActivos): array
    {
        $bruta = Dinero::cero();
        $deterioro = Dinero::cero();

        foreach ($filasActivos as $fila) {
            if (str_starts_with($fila['codigo'], ContabilidadConstants::PREFIJOS_CUENTAS_POR_COBRAR[0])) {
                $bruta = $bruta->sumar($fila['valor']);
            } elseif (str_starts_with($fila['codigo'], ContabilidadConstants::PREFIJO_CUENTA_DETERIORO_CARTERA)) {
                $deterioro = $deterioro->sumar($fila['valor']);
            }
        }

        $porcentaje = $bruta->esCero() ? '0.00' : number_format($deterioro->absoluto()->aFloat() / $bruta->aFloat() * 100, 2);

        return [
            'bruta' => $bruta,
            'deterioro' => $deterioro->absoluto(),
            'neta' => $bruta->sumar($deterioro),
            'porcentaje_deterioro' => $porcentaje,
        ];
    }

    private function gastosPorCentroCosto(int $empresaId, string $desde, string $hasta): array
    {
        return $this->centrosCosto->gastosPorCentroCosto($empresaId, $desde, $hasta)
            ->map(fn ($fila) => [
                'centro_costo_id' => $fila->centro_costo_id,
                'nombre' => $fila->nombre,
                'valor' => Dinero::desdeCentavos((int) $fila->valor),
            ])
            ->all();
    }

    /** Una sola consulta agregada por mes (no 12 llamadas a `estadosFinancieros`). */
    private function serieMensual(int $empresaId, string $hasta): array
    {
        $desde = Carbon::parse($hasta)->subMonths(11)->startOfMonth()->toDateString();
        $tipos = [TipoCuenta::Ingreso->value, TipoCuenta::Gasto->value, TipoCuenta::Costo->value];
        $filas = $this->asientos->saldosMensualesPorTipo($empresaId, $tipos, $desde, $hasta);

        $meses = [];
        $cursor = Carbon::parse($desde);

        for ($i = 0; $i < 12; $i++) {
            $meses[$cursor->format('Y-m')] = ['ingresos' => Dinero::cero(), 'gastos' => Dinero::cero()];
            $cursor->addMonth();
        }

        foreach ($filas as $fila) {
            if (! isset($meses[$fila->mes])) {
                continue;
            }

            $debitos = Dinero::desdeCentavos((int) $fila->debitos);
            $creditos = Dinero::desdeCentavos((int) $fila->creditos);

            if ($fila->tipo === TipoCuenta::Ingreso->value) {
                $meses[$fila->mes]['ingresos'] = $meses[$fila->mes]['ingresos']->sumar($creditos->restar($debitos));
            } else {
                $meses[$fila->mes]['gastos'] = $meses[$fila->mes]['gastos']->sumar($debitos->restar($creditos));
            }
        }

        return array_map(fn (string $mes, array $valores) => [
            'mes' => $mes,
            'ingresos' => $valores['ingresos'],
            'gastos' => $valores['gastos'],
            'utilidad' => $valores['ingresos']->restar($valores['gastos']),
        ], array_keys($meses), $meses);
    }

    private function periodoAbierto(string $hasta): array
    {
        $periodo = $this->periodos->obtenerOCrear((int) substr($hasta, 0, 4), (int) substr($hasta, 5, 2));

        return ['anio' => $periodo->anio, 'mes' => $periodo->mes, 'estado' => $periodo->estado->value];
    }

    private function solicitudesPendientes(): array
    {
        return $this->solicitudesReapertura->pendientes()
            ->map(fn ($solicitud) => [
                'id' => $solicitud->id,
                'periodo' => $solicitud->periodo ? ['anio' => $solicitud->periodo->anio, 'mes' => $solicitud->periodo->mes] : null,
                'solicitante' => $solicitud->solicitante?->nombre,
                'creado_en' => $solicitud->creado_en->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Saldo operativo: suma de `valor + valor_otros_gastos - valor_pagado` de las cuotas de créditos vigentes
     * (lado "Creditos"/"Cartera"). Saldo contable: 1305 bruta menos 1399 deterioro a la misma fecha (lado "libros").
     * `saldosParaBalancePrueba()` se consulta una sola vez aquí; `ControlesCierreService::ejecutar()` (llamado por
     * `cierre()`) hace sus propias llamadas independientes a la misma consulta para CxC/CxP — redundante pero
     * aceptado: es un reporte de solo lectura, no un camino caliente, y tocar la firma de `ControlesCierreService`
     * para compartirla queda fuera de alcance de este cambio.
     */
    private function carteraOperativaVsContable(int $empresaId, string $hasta): array
    {
        $resumen = $this->cuotas->resumenPorCreditos($this->creditos->idsVigentesDeEmpresa($empresaId));
        $saldoOperativo = Dinero::sumarVarios(...array_map(fn (array $fila) => Dinero::desdeCentavos($fila['saldo']), $resumen))->noNegativo();

        $filas = $this->cuentas->saldosParaBalancePrueba($empresaId, $hasta);
        $saldoCxc = Dinero::sumarVarios(...$filas
            ->filter(fn ($fila) => str_starts_with($fila->codigo, ContabilidadConstants::PREFIJOS_CUENTAS_POR_COBRAR[0]))
            ->map(fn ($fila) => $this->saldoNatural($fila))
            ->all());
        $deterioro = Dinero::sumarVarios(...$filas
            ->filter(fn ($fila) => str_starts_with($fila->codigo, ContabilidadConstants::PREFIJO_CUENTA_DETERIORO_CARTERA))
            ->map(fn ($fila) => $this->saldoNatural($fila))
            ->all());
        $saldoContable = $saldoCxc->restar($deterioro);

        return [
            'saldo_operativo' => $saldoOperativo,
            'saldo_contable' => $saldoContable,
            'diferencia' => $saldoOperativo->restar($saldoContable),
        ];
    }

    private function saldoNatural(object $fila): Dinero
    {
        $debitos = Dinero::desdeCentavos((int) $fila->debitos);
        $creditos = Dinero::desdeCentavos((int) $fila->creditos);

        return $fila->naturaleza === NaturalezaCuenta::Debito ? $debitos->restar($creditos) : $creditos->restar($debitos);
    }

    private function devengoIntereses(): array
    {
        $ejecucion = $this->ejecucionesTareas->ultimasPorClave([ContabilidadConstants::TAREA_DEVENGAR_INTERESES])[ContabilidadConstants::TAREA_DEVENGAR_INTERESES] ?? null;

        return [
            'corrio_este_mes' => $ejecucion !== null && Carbon::parse($ejecucion['finalizado_en'])->isSameMonth(now()),
            'resultado' => $ejecucion['resultado'] ?? null,
            'finalizado_en' => $ejecucion['finalizado_en'] ?? null,
        ];
    }

    /** No hay N+1: `todasPorEvento()` trae todas las reglas de la empresa en una sola consulta. */
    private function eventosSinRegla(): array
    {
        $reglas = $this->reglas->todasPorEvento();

        return collect(EventoContable::cases())
            ->reject(fn (EventoContable $evento) => ($reglas[$evento->value] ?? null)?->activo === true)
            ->map(fn (EventoContable $evento) => ['evento' => $evento->value, 'etiqueta' => $evento->etiqueta()])
            ->values()
            ->all();
    }
}
