<?php

namespace App\Creditos\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Contabilidad\Services\ComprobanteService;
use App\Core\Dinero\Dinero;
use App\Creditos\Enums\EstadoCredito;
use App\Creditos\Enums\TipoCredito;
use App\Creditos\Enums\TipoPago;
use App\Creditos\Exportaciones\FuenteCreditos;
use App\Creditos\Models\Credito;
use App\Creditos\Repositories\CreditoRepository;
use App\Creditos\Repositories\CuotaRepository;
use App\Exportacion\Enums\FormatoExportacion;
use App\Exportacion\Models\Exportacion;
use App\Exportacion\Services\ExportacionService;
use App\Usuarios\Models\Usuario;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditoService
{
    public function __construct(
        protected CreditoRepository $creditos,
        protected CuotaRepository $cuotas,
        protected CuotaService $cuotaService,
        protected ComprobanteService $comprobantes,
        protected EmisorAuditoria $auditoria,
        protected ExportacionService $exportaciones,
    ) {}

    public function listarCreditos(?string $busqueda, int $porPagina, int $pagina, ?int $clienteId = null, ?EstadoCredito $estado = null): LengthAwarePaginator
    {
        $this->cuotaService->actualizarVencidas();

        return $this->creditos->paginar($busqueda, max(1, min(100, $porPagina)), max(1, $pagina), $clienteId, $estado);
    }

    public function solicitarExportacion(Usuario $usuario, ?string $busqueda, ?EstadoCredito $estado, FormatoExportacion $formato): Exportacion
    {
        $this->cuotaService->actualizarVencidas();

        return $this->exportaciones->solicitar(
            new FuenteCreditos((int) $usuario->empresa_id, $usuario->idAsesorConAlcance(), $busqueda, $estado?->value),
            $formato,
            $usuario,
        );
    }

    public function crearCredito(array $datos): Credito
    {
        return DB::transaction(function () use ($datos) {
            [$valorPrestamo, $saldoTomado] = $this->valorDelPrestamo($datos);

            $credito = $this->creditos->crear([
                'cliente_id' => $datos['cliente_id'],
                'tipo_credito' => $datos['tipo_credito'],
                'credito_origen_id' => $datos['credito_origen_id'] ?? null,
                'valor_prestamo' => $valorPrestamo,
                'saldo_refinanciado' => $saldoTomado,
                'tasa_interes' => $datos['tasa_interes'],
                'plazo' => $datos['plazo'],
                'tipo_pago' => $datos['tipo_pago'],
                'valor_cuota' => 0,
                'valor_total' => 0,
                'fecha_desembolso' => $datos['fecha_desembolso'],
                'estado' => EstadoCredito::Solicitado,
                'creado_por' => Auth::id(),
            ]);

            $resultado = AmortizacionCalculator::calcular(
                $valorPrestamo,
                (float) $datos['tasa_interes'],
                (int) $datos['plazo'],
                TipoPago::from($datos['tipo_pago']),
                $datos['fecha_desembolso'],
            );

            $this->cuotas->crearVarias($credito->id, $resultado['cuotas']);

            $this->creditos->actualizar($credito, [
                'valor_cuota' => $resultado['valor_cuota'],
                'valor_total' => $resultado['valor_total'],
            ]);

            $this->auditoria->registrarCreacion(
                AuditoriaConstants::MODULO_CREDITOS,
                "Crédito #{$credito->id} creado para el cliente #{$credito->cliente_id}",
                $credito,
                ['cuotas' => count($resultado['cuotas'])],
            );

            return $this->creditos->obtenerConRelaciones($credito);
        });
    }

    /**
     * Solo mientras el crédito no se ha desembolsado: aún no hay pagos ni comprobante contable, así que
     * cambiar las condiciones se resuelve regenerando el plan de cuotas. Pasado el desembolso, cualquier
     * corrección va por refinanciación. La bitácora conserva valores anteriores y nuevos, motivo, usuario y fecha.
     */
    public function editarCondiciones(Credito $credito, array $datos): Credito
    {
        return DB::transaction(function () use ($credito, $datos) {
            $credito = $this->creditos->bloquear($credito->id);

            if (! in_array($credito->estado, [EstadoCredito::Solicitado, EstadoCredito::Aprobado], true)) {
                throw ValidationException::withMessages(['credito' => __('validation.custom.credito.no_editable')]);
            }

            $valorPrestamo = $credito->tipo_credito === TipoCredito::Refinanciacion
                ? ($credito->saldo_refinanciado ?? Dinero::cero())->sumar(Dinero::desdePesos($datos['valor_adicional']))
                : Dinero::desdePesos($datos['valor_prestamo']);

            if (! $valorPrestamo->esPositivo()) {
                throw ValidationException::withMessages(['valor_adicional' => __('validation.custom.valor_adicional.sin_valor')]);
            }

            $anteriores = $this->auditoria->instantanea($credito);
            $cuotasReemplazadas = $this->cuotas->todasOrdenadasParaActualizar($credito->id)->count();

            $resultado = AmortizacionCalculator::calcular(
                $valorPrestamo,
                (float) $datos['tasa_interes'],
                (int) $datos['plazo'],
                TipoPago::from($datos['tipo_pago']),
                $datos['fecha_desembolso'],
            );

            $this->cuotas->eliminarTodasDeCredito($credito->id);
            $this->cuotas->crearVarias($credito->id, $resultado['cuotas']);

            $credito = $this->creditos->actualizar($credito, [
                'valor_prestamo' => $valorPrestamo,
                'tasa_interes' => $datos['tasa_interes'],
                'plazo' => (int) $datos['plazo'],
                'tipo_pago' => $datos['tipo_pago'],
                'fecha_desembolso' => $datos['fecha_desembolso'],
                'valor_cuota' => $resultado['valor_cuota'],
                'valor_total' => $resultado['valor_total'],
            ]);

            $this->auditoria->registrarModificacion(
                AuditoriaConstants::MODULO_CREDITOS,
                "Condiciones del crédito #{$credito->id} modificadas",
                $credito,
                $anteriores,
                motivo: $datos['motivo'],
                adicionales: [
                    'cuotas_reemplazadas' => $cuotasReemplazadas,
                    'cuotas_generadas' => count($resultado['cuotas']),
                    'efecto_contable' => 'ninguno: el crédito aún no se desembolsa',
                ],
            );

            return $this->creditos->obtenerConRelaciones($credito);
        });
    }

    /**
     * Refinanciar es unificar el saldo de capital pendiente del original con el nuevo valor a
     * prestar en un solo crédito (definición de DDA). El saldo tomado se guarda como foto.
     *
     * @return array{0: Dinero, 1: ?Dinero} valor del préstamo y saldo tomado (nulo si no es refinanciación)
     */
    private function valorDelPrestamo(array $datos): array
    {
        if ($datos['tipo_credito'] !== TipoCredito::Refinanciacion->value) {
            return [Dinero::desdePesos($datos['valor_prestamo']), null];
        }

        $saldoTomado = $this->cuotaService->capitalPendiente($this->creditos->buscarPorIdOFallar($datos['credito_origen_id']));
        $valorPrestamo = $saldoTomado->sumar(Dinero::desdePesos($datos['valor_adicional']));

        if (! $valorPrestamo->esPositivo()) {
            throw ValidationException::withMessages(['valor_adicional' => __('validation.custom.valor_adicional.sin_valor')]);
        }

        return [$valorPrestamo, $saldoTomado];
    }

    /**
     * Vínculo entre el crédito original y su refinanciación, visible desde ambos lados en el
     * detalle y en el estado de cuenta. Requiere `obtenerConRelaciones` (usa las relaciones ya cargadas).
     *
     * @return array<string, mixed>|null
     */
    public function vinculoRefinanciacion(Credito $credito): ?array
    {
        if ($credito->tipo_credito === TipoCredito::Refinanciacion && $credito->credito_origen) {
            return [
                'tipo' => 'refinancia',
                'credito_relacionado' => $this->resumenDelVinculo($credito->credito_origen),
                'saldo_tomado' => $credito->saldo_refinanciado,
                'valor_adicional' => $credito->saldo_refinanciado === null ? null : $credito->valorDesembolsado(),
            ];
        }

        if ($credito->estado === EstadoCredito::Refinanciado && $credito->refinanciado_por) {
            return [
                'tipo' => 'refinanciado',
                'credito_relacionado' => $this->resumenDelVinculo($credito->refinanciado_por),
            ];
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function resumenDelVinculo(Credito $credito): array
    {
        return [
            'id' => $credito->id,
            'estado' => $credito->estado,
            'valor_prestamo' => $credito->valor_prestamo,
            'fecha_desembolso' => $credito->fecha_desembolso,
        ];
    }

    public function mostrarCredito(Credito $credito): Credito
    {
        $this->cuotaService->actualizarVencidas();

        return $this->creditos->obtenerConRelaciones($credito);
    }

    public function aprobar(Credito $credito): Credito
    {
        $this->exigirEstado($credito, EstadoCredito::Solicitado);

        $anteriores = $this->auditoria->instantanea($credito);
        $credito = $this->creditos->actualizar($credito, ['estado' => EstadoCredito::Aprobado]);

        $this->auditoria->registrarModificacion(
            AuditoriaConstants::MODULO_CREDITOS,
            "Crédito #{$credito->id} aprobado",
            $credito,
            $anteriores,
            accion: AccionAuditoria::Aprobar,
        );

        return $credito;
    }

    /** Recién aquí se contabiliza el desembolso — antes de aprobar/desembolsar no ha salido dinero real. */
    public function desembolsar(Credito $credito): Credito
    {
        return DB::transaction(function () use ($credito) {
            $this->exigirEstado($credito, EstadoCredito::Aprobado);

            $origen = $this->origenParaCongelar($credito);
            $anteriores = $this->auditoria->instantanea($credito);

            $credito = $this->creditos->actualizar($credito, [
                'estado' => EstadoCredito::Desembolsado,
                'desembolsado_en' => now(),
            ]);
            $comprobante = $this->comprobantes->contabilizarDesembolsoCredito($credito);

            $this->auditoria->registrarModificacion(
                AuditoriaConstants::MODULO_CREDITOS,
                "Crédito #{$credito->id} desembolsado".($comprobante ? '' : ' sin contabilizar (sin regla activa o sin dinero nuevo)'),
                $credito,
                $anteriores,
                accion: AccionAuditoria::Desembolsar,
                adicionales: ['comprobante_id' => $comprobante?->id, 'contabilizado' => $comprobante !== null],
            );

            if ($origen) {
                $this->congelarOrigen($origen, $credito);
            }

            return $credito;
        });
    }

    /** El original sigue recibiendo pagos hasta que se desembolsa la refinanciación; debe seguir desembolsado en ese momento. */
    private function origenParaCongelar(Credito $credito): ?Credito
    {
        if ($credito->tipo_credito !== TipoCredito::Refinanciacion || $credito->credito_origen_id === null) {
            return null;
        }

        $origen = $this->creditos->buscarParaCongelar($credito->credito_origen_id);

        if ($origen->estado !== EstadoCredito::Desembolsado) {
            throw ValidationException::withMessages(['credito_origen_id' => __('validation.custom.credito_origen_id.origen_no_desembolsado')]);
        }

        // El original siguió recibiendo pagos desde la solicitud: si su saldo cambió, la base de la refinanciación ya no es la acordada.
        if ($credito->saldo_refinanciado !== null && ! $this->cuotaService->capitalPendiente($origen)->igualA($credito->saldo_refinanciado)) {
            throw ValidationException::withMessages(['credito_origen_id' => __('validation.custom.credito_origen_id.saldo_cambio')]);
        }

        return $origen;
    }

    /** El original queda congelado como historial: sus cuotas no se tocan ni se inventan pagos. */
    private function congelarOrigen(Credito $origen, Credito $refinanciacion): void
    {
        $anteriores = $this->auditoria->instantanea($origen);

        $this->creditos->marcarComoRefinanciado($origen);

        $this->auditoria->registrarModificacion(
            AuditoriaConstants::MODULO_CREDITOS,
            "Crédito #{$origen->id} refinanciado por el crédito #{$refinanciacion->id}",
            $origen,
            $anteriores,
        );
    }

    /** Desembolsada, la refinanciación ya congeló al original: deshacerla dejaría a ambos sin cobrar. */
    private function exigirNoSerRefinanciacionDesembolsada(Credito $credito): void
    {
        if ($credito->tipo_credito === TipoCredito::Refinanciacion && $credito->estado === EstadoCredito::Desembolsado) {
            throw ValidationException::withMessages(['credito' => __('validation.custom.credito.refinanciacion_desembolsada')]);
        }
    }

    /**
     * Solicitado/Aprobado se pueden cancelar en cualquier momento (aún no hay dinero
     * real de por medio). Un crédito Desembolsado solo se puede cancelar dentro de
     * las 24 horas siguientes al desembolso — pasada esa ventana, la corrección pasa
     * por el flujo normal de pagos/liquidación, no por cancelación. Un crédito con al
     * menos un pago registrado tampoco se puede cancelar, sin importar cuánto tiempo
     * lleve desembolsado (mismo criterio que `eliminar()`): un pago ya es un hecho
     * financiero real, no algo que "cancelar" pueda deshacer. Si el crédito ya estaba
     * desembolsado, su comprobante de desembolso se reversa (nunca se edita ni se
     * borra) para que la contabilidad no quede con dinero "salido" de un crédito
     * cancelado.
     */
    public function cancelar(Credito $credito): Credito
    {
        return DB::transaction(function () use ($credito) {
            $this->exigirNoSerRefinanciacionDesembolsada($credito);

            $puedeCancelar = match ($credito->estado) {
                EstadoCredito::Solicitado, EstadoCredito::Aprobado => true,
                EstadoCredito::Desembolsado => $credito->desembolsado_en !== null
                    && $credito->desembolsado_en->diffInHours(now()) < 24,
                default => false,
            };

            if (! $puedeCancelar) {
                throw ValidationException::withMessages(['estado' => __('validation.custom.estado.transicion_invalida')]);
            }

            if ($credito->pagos()->exists()) {
                throw ValidationException::withMessages(['credito' => __('validation.custom.credito.cancelacion_con_pagos')]);
            }

            $anteriores = $this->auditoria->instantanea($credito);

            if ($credito->estado === EstadoCredito::Desembolsado) {
                $this->reversarDesembolsoSiExiste($credito, 'Cancelación del crédito #'.$credito->id.' dentro de las 24 horas del desembolso');
            }

            $credito = $this->creditos->actualizar($credito, ['estado' => EstadoCredito::Cancelado]);

            $this->auditoria->registrarModificacion(
                AuditoriaConstants::MODULO_CREDITOS,
                "Crédito #{$credito->id} cancelado",
                $credito,
                $anteriores,
                accion: AccionAuditoria::Cancelar,
            );

            return $credito;
        });
    }

    /**
     * Soft delete — nunca hard delete de un registro de negocio. Se bloquea si el
     * crédito ya tiene pagos registrados (igual que `ClienteService::eliminar()`
     * bloquea por créditos activos): un crédito con historial de pagos es un hecho
     * financiero real, no un error a "deshacer". Si estaba desembolsado, reversa su
     * comprobante por la misma razón que `cancelar()`.
     */
    public function eliminar(Credito $credito): bool
    {
        return DB::transaction(function () use ($credito) {
            if ($credito->estado === EstadoCredito::Refinanciado) {
                throw ValidationException::withMessages(['credito' => __('validation.custom.credito.refinanciado_no_eliminable')]);
            }

            $this->exigirNoSerRefinanciacionDesembolsada($credito);

            if ($credito->pagos()->exists()) {
                throw ValidationException::withMessages(['credito' => __('validation.custom.credito.tiene_pagos_registrados')]);
            }

            if ($credito->estado === EstadoCredito::Desembolsado) {
                $this->reversarDesembolsoSiExiste($credito, 'Eliminación del crédito #'.$credito->id);
            }

            $this->cuotas->eliminarTodasDeCredito($credito->id);

            $eliminado = $this->creditos->eliminar($credito);

            $this->auditoria->registrarEliminacion(AuditoriaConstants::MODULO_CREDITOS, "Crédito #{$credito->id} eliminado", $credito);

            return $eliminado;
        });
    }

    private function reversarDesembolsoSiExiste(Credito $credito, string $motivo): void
    {
        $comprobante = $this->comprobantes->buscarContabilizadoPorOrigen('creditos', $credito->id);

        if ($comprobante) {
            $this->comprobantes->reversarComprobante($comprobante, $motivo);
        }
    }

    private function exigirEstado(Credito $credito, EstadoCredito $esperado): void
    {
        if ($credito->estado !== $esperado) {
            throw ValidationException::withMessages(['estado' => __('validation.custom.estado.transicion_invalida')]);
        }
    }
}
