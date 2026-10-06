<?php

namespace App\Pagos\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Services\EmisorAuditoria;
use App\Contabilidad\Services\ComprobanteService;
use App\Core\Dinero\Dinero;
use App\Creditos\Enums\EstadoCredito;
use App\Creditos\Enums\EstadoCuota;
use App\Creditos\Models\Credito;
use App\Creditos\Repositories\CreditoRepository;
use App\Creditos\Repositories\CuotaRepository;
use App\Creditos\Services\OtrosGastosCalculator;
use App\Exportacion\Enums\FormatoExportacion;
use App\Exportacion\Models\Exportacion;
use App\Exportacion\Services\ExportacionService;
use App\Pagos\Enums\FormaPago;
use App\Pagos\Exportaciones\FuentePagos;
use App\Pagos\Models\Pago;
use App\Pagos\Repositories\PagoDetalleRepository;
use App\Pagos\Repositories\PagoRepository;
use App\Recibos\Services\ReciboCajaService;
use App\Usuarios\Models\Usuario;
use Carbon\CarbonImmutable;
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
        protected ReciboCajaService $recibos,
        protected EmisorAuditoria $auditoria,
        protected ExportacionService $exportaciones,
    ) {}

    public function solicitarExportacion(Usuario $usuario, ?string $busqueda, FormatoExportacion $formato): Exportacion
    {
        return $this->exportaciones->solicitar(
            new FuentePagos((int) $usuario->empresa_id, $usuario->idAsesorConAlcance(), $busqueda),
            $formato,
            $usuario,
        );
    }

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
            $creditoAnteriores = $this->auditoria->instantanea($credito);

            $pago = $this->pagos->crear([
                'credito_id' => $credito->id,
                'cliente_id' => $credito->cliente_id,
                'valor_pago' => $valorPago,
                'fecha_pago' => now(),
                'forma_pago' => $formaPago,
                'observacion' => $observacion,
                'registrado_por' => $usuarioId,
            ]);

            $aplicacion = $this->aplicarAlPlan($credito, $pago, $valorPago);
            $this->pagos->actualizar($pago, ['valor_capital' => $aplicacion['capital'], 'valor_interes' => $aplicacion['interes'], 'valor_otros_gastos' => $aplicacion['otros']]);
            $restante = $aplicacion['sobrante'];
            $capitalTotal = $aplicacion['capital'];
            $interesTotal = $aplicacion['interes'];
            $otrosTotal = $aplicacion['otros'];
            $cuotasAfectadas = $aplicacion['cuotas'];

            $this->recalcularSaldoCapital($credito);

            if ($this->cuotas->contarNoPagadas($credito->id) === 0) {
                $this->creditos->marcarComoPagado($credito);
            } else {
                $this->ajustarOtrosGastosRestantes($credito);
            }

            $comprobante = $this->comprobantes->contabilizarRecaudoPago($pago, $capitalTotal, $interesTotal, $otrosTotal);
            $recibo = $this->recibos->emitir($pago, $comprobante);

            $this->auditoria->registrarCreacion(
                AuditoriaConstants::MODULO_PAGOS,
                "Pago #{$pago->id} de {$valorPago->aDecimalString()} registrado en el crédito #{$credito->id}",
                $pago,
                [
                    'capital' => $capitalTotal,
                    'interes' => $interesTotal,
                    'otros_gastos' => $otrosTotal,
                    'sobrante_descartado' => $restante,
                    'cuotas_afectadas' => $cuotasAfectadas,
                    'comprobante_id' => $comprobante?->id,
                    'recibo' => $recibo->numero,
                ],
            );
            $this->auditoria->registrarModificacion(
                AuditoriaConstants::MODULO_CREDITOS,
                "Crédito #{$credito->id} saldado con el pago #{$pago->id}",
                $credito,
                $creditoAnteriores,
            );

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

        $divisorPeriodo = $credito->tipo_pago->diasPorPeriodo();
        $tasaDiaria = ((float) $credito->tasa_interes / 100) / $divisorPeriodo;

        $interes = $capitalPendiente->multiplicarPorTasa($tasaDiaria * $diasTranscurridos);
        $otrosGastos = $this->otrosGastosLiquidacion($credito);
        $total = $capitalPendiente->sumar($interes)->sumar($otrosGastos);

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
            'otros_gastos' => $otrosGastos,
            'total' => $total,
            'fecha_referencia' => $fechaReferencia->toDateString(),
        ];
    }

    public function liquidarCredito(Credito $credito, FormaPago $formaPago, ?string $observacion, ?int $usuarioId): array
    {
        return DB::transaction(function () use ($credito, $formaPago, $observacion, $usuarioId) {
            $creditoAnteriores = $this->auditoria->instantanea($credito);
            $cotizacion = $this->calcularLiquidacion($credito);

            $pago = $this->pagos->crear([
                'credito_id' => $credito->id,
                'cliente_id' => $credito->cliente_id,
                'valor_pago' => $cotizacion['total'],
                'valor_capital' => $cotizacion['capital_pendiente'],
                'valor_interes' => $cotizacion['interes'],
                'valor_otros_gastos' => $cotizacion['otros_gastos'],
                'fecha_pago' => now(),
                'forma_pago' => $formaPago,
                'observacion' => $observacion ?? 'Liquidación anticipada del crédito',
                'registrado_por' => $usuarioId,
            ]);

            $this->cuotas->cerrarPendientesComoOrigen($credito->id);
            $this->recalcularSaldoCapital($credito);
            $this->creditos->marcarComoPagado($credito);
            $comprobante = $this->comprobantes->contabilizarRecaudoPago($pago, $cotizacion['capital_pendiente'], $cotizacion['interes'], $cotizacion['otros_gastos']);
            $recibo = $this->recibos->emitir($pago, $comprobante);

            $this->auditoria->registrarCreacion(
                AuditoriaConstants::MODULO_PAGOS,
                "Liquidación anticipada del crédito #{$credito->id}: pago #{$pago->id} de {$cotizacion['total']->aDecimalString()}",
                $pago,
                [
                    'capital' => $cotizacion['capital_pendiente'],
                    'interes' => $cotizacion['interes'],
                    'comprobante_id' => $comprobante?->id,
                    'recibo' => $recibo->numero,
                ],
            );
            $this->auditoria->registrarModificacion(
                AuditoriaConstants::MODULO_CREDITOS,
                "Crédito #{$credito->id} saldado por liquidación anticipada",
                $credito,
                $creditoAnteriores,
            );

            return ['pago' => $pago, ...$cotizacion];
        });
    }

    /**
     * Pago de la importación histórica: fecha del archivo, sin recibo de caja. Solo contabiliza si la carga lo pidió.
     * Un valor que excede el saldo pendiente no se descarta: revierte la transacción con el mensaje correspondiente.
     *
     * @param  array<string, mixed>  $datosOrigen  texto del archivo que no es columna del pago (cuenta, registrado por)
     */
    public function registrarPagoHistorico(
        Credito $credito,
        Dinero $valor,
        string $fechaPago,
        array $datosOrigen,
        ?int $registradoPor,
        bool $contabilizar,
    ): Pago {
        $this->exigirDesembolsado($credito);

        return DB::transaction(function () use ($credito, $valor, $fechaPago, $datosOrigen, $registradoPor, $contabilizar) {
            $creditoAnteriores = $this->auditoria->instantanea($credito);

            $pago = $this->pagos->crear([
                'credito_id' => $credito->id,
                'cliente_id' => $credito->cliente_id,
                'valor_pago' => $valor,
                'fecha_pago' => CarbonImmutable::parse($fechaPago, 'America/Bogota')->startOfDay(),
                'forma_pago' => FormaPago::Otro,
                'observacion' => $datosOrigen['observaciones'] ?? null,
                'registrado_por' => $registradoPor,
            ]);

            $aplicacion = $this->aplicarAlPlan($credito, $pago, $valor);
            $this->pagos->actualizar($pago, ['valor_capital' => $aplicacion['capital'], 'valor_interes' => $aplicacion['interes'], 'valor_otros_gastos' => $aplicacion['otros']]);

            if (! $aplicacion['sobrante']->esCero()) {
                throw ValidationException::withMessages(['valor' => 'El valor supera el saldo pendiente del crédito.']);
            }

            $this->recalcularSaldoCapital($credito);

            if ($this->cuotas->contarNoPagadas($credito->id) === 0) {
                $this->creditos->marcarComoPagado($credito);
            }

            $comprobante = $contabilizar
                ? $this->comprobantes->contabilizarRecaudoPago($pago, $aplicacion['capital'], $aplicacion['interes'], $aplicacion['otros'])
                : null;

            $this->auditoria->registrarCreacion(
                AuditoriaConstants::MODULO_PAGOS,
                "Pago #{$pago->id} de {$valor->aDecimalString()} importado en el crédito #{$credito->id}",
                $pago,
                [
                    'capital' => $aplicacion['capital'],
                    'interes' => $aplicacion['interes'],
                    'cuotas_afectadas' => $aplicacion['cuotas'],
                    'comprobante_id' => $comprobante?->id,
                    'importado' => true,
                    'origen_archivo' => $datosOrigen,
                ],
            );
            $this->auditoria->registrarModificacion(
                AuditoriaConstants::MODULO_CREDITOS,
                "Crédito #{$credito->id} actualizado por pago importado #{$pago->id}",
                $credito,
                $creditoAnteriores,
            );

            return $pago;
        });
    }

    /**
     * @return array{sobrante: Dinero, capital: Dinero, interes: Dinero, otros: Dinero, cuotas: list<int>}
     */
    private function aplicarAlPlan(Credito $credito, Pago $pago, Dinero $valor): array
    {
        $cuotas = $this->cuotas->pendientesOrdenadasParaActualizar($credito->id)->values();

        $aplicacion = AplicadorPagoPlan::aplicar(
            $cuotas->map(fn ($cuota) => [
                'valor' => $cuota->valor->sumar($cuota->valor_otros_gastos),
                'valor_pagado' => $cuota->valor_pagado,
                'valor_interes' => $cuota->valor_interes,
                'valor_otros_gastos' => $cuota->valor_otros_gastos,
            ])->all(),
            $valor,
        );

        $cuotasAfectadas = [];

        foreach ($aplicacion['asignaciones'] as $asignacion) {
            $cuota = $cuotas[$asignacion['posicion']];

            $this->pagoDetalles->crear([
                'pago_id' => $pago->id,
                'cuota_id' => $cuota->id,
                'valor_aplicado' => $asignacion['aplicado'],
            ]);

            $cuotasAfectadas[] = $cuota->id;

            $this->cuotas->actualizar($cuota, [
                'valor_pagado' => $asignacion['nuevo_pagado'],
                'estado' => $asignacion['nuevo_pagado']->mayorOIgualQue($cuota->valor->sumar($cuota->valor_otros_gastos)) ? EstadoCuota::Pagada : EstadoCuota::Pendiente,
            ]);
        }

        return [
            'sobrante' => $aplicacion['sobrante'],
            'capital' => $aplicacion['capital'],
            'interes' => $aplicacion['interes'],
            'otros' => $aplicacion['otros'],
            'cuotas' => $cuotasAfectadas,
        ];
    }

    /**
     * Otros gastos que cobra una cancelación anticipada (regla 12): las cuotas vencidas no pagadas cobran su saldo
     * completo de otros gastos; la cuota del periodo en curso cobra lo devengado por días desde el inicio de su periodo.
     * Las cuotas posteriores no generan otros gastos.
     */
    private function otrosGastosLiquidacion(Credito $credito): Dinero
    {
        $hoy = now()->startOfDay();
        $inicioPeriodo = $credito->fecha_desembolso;
        $total = Dinero::cero();

        foreach ($this->cuotas->todasPorCredito($credito->id) as $cuota) {
            $inicio = $inicioPeriodo;
            $inicioPeriodo = $cuota->fecha_vencimiento;

            if ($cuota->estado === EstadoCuota::Pagada) {
                continue;
            }

            $pendiente = $cuota->valor_otros_gastos->restar(AplicadorPagoPlan::desglosePagado($cuota->valor_pagado, $cuota->valor_interes, $cuota->valor_otros_gastos)['otros'])->noNegativo();

            if ($cuota->fecha_vencimiento->lt($hoy)) {
                $total = $total->sumar($pendiente);
            } elseif ($inicio->lte($hoy)) {
                $devengado = OtrosGastosCalculator::proporcionalPorDias($cuota->valor_otros_gastos, (int) $inicio->diffInDays(now()), $credito->tipo_pago);
                $total = $total->sumar($pendiente->menorEntre($devengado));
            }
        }

        return $total;
    }

    /**
     * Abono parcial que no liquida el crédito (regla 13): los otros gastos de las cuotas restantes pasan a
     * capital pendiente × porcentaje, es decir, capital inicial × porcentaje × (saldo / capital inicial).
     * Solo cambian las cuotas sin ningún pago; una cuota con pago parcial conserva su valor.
     */
    private function ajustarOtrosGastosRestantes(Credito $credito): void
    {
        $saldo = $this->capitalPendienteReal($credito);
        $otrosAjustados = OtrosGastosCalculator::valorPorCuota($saldo, (float) $credito->gastos_administracion_pct);

        foreach ($this->cuotas->pendientesOrdenadasParaActualizar($credito->id) as $cuota) {
            // Una cuota con pago parcial en curso conserva su valor: cambiarlo a mitad de pago alteraría lo ya abonado.
            if (! $cuota->valor_pagado->esCero()) {
                continue;
            }

            $this->cuotas->actualizar($cuota, ['valor_otros_gastos' => $otrosAjustados]);
        }
    }

    private function exigirDesembolsado(Credito $credito): void
    {
        if ($credito->estado === EstadoCredito::Refinanciado) {
            throw ValidationException::withMessages(['estado' => __('validation.custom.estado.refinanciado')]);
        }

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
            $capitalRestante = $capitalRestante->restar(AplicadorPagoPlan::desglosePagado($cuota->valor_pagado, $cuota->valor_interes, $cuota->valor_otros_gastos)['capital']);
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
            $capitalPagado = AplicadorPagoPlan::desglosePagado($cuota->valor_pagado, $cuota->valor_interes, $cuota->valor_otros_gastos)['capital'];
            $capitalRestante = $capitalRestante->restar($capitalPagado);
            $this->cuotas->actualizar($cuota, ['saldo_capital' => $capitalRestante->noNegativo()]);
        }
    }
}
