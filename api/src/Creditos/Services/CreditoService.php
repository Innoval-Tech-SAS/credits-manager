<?php

namespace App\Creditos\Services;

use App\Contabilidad\Services\ComprobanteService;
use App\Core\Dinero\Dinero;
use App\Creditos\Enums\EstadoCredito;
use App\Creditos\Enums\TipoCredito;
use App\Creditos\Enums\TipoPago;
use App\Creditos\Models\Credito;
use App\Creditos\Repositories\CreditoRepository;
use App\Creditos\Repositories\CuotaRepository;
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
    ) {}

    public function listarCreditos(?string $busqueda, int $porPagina, int $pagina, ?int $clienteId = null, ?EstadoCredito $estado = null): LengthAwarePaginator
    {
        $this->cuotaService->actualizarVencidas();

        return $this->creditos->paginar($busqueda, max(1, min(100, $porPagina)), max(1, $pagina), $clienteId, $estado);
    }

    public function crearCredito(array $datos): Credito
    {
        return DB::transaction(function () use ($datos) {
            $credito = $this->creditos->crear([
                'cliente_id' => $datos['cliente_id'],
                'tipo_credito' => $datos['tipo_credito'],
                'credito_origen_id' => $datos['credito_origen_id'] ?? null,
                'valor_prestamo' => $datos['valor_prestamo'],
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
                Dinero::desdePesos($datos['valor_prestamo']),
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

            if ($credito->tipo_credito === TipoCredito::Refinanciacion) {
                $origen = $this->creditos->buscarPorIdOFallar($datos['credito_origen_id']);
                $this->creditos->marcarComoPagado($origen);
                $this->cuotas->cerrarPendientesComoOrigen($origen->id);
            }

            return $this->creditos->obtenerConRelaciones($credito);
        });
    }

    public function mostrarCredito(Credito $credito): Credito
    {
        $this->cuotaService->actualizarVencidas();

        return $this->creditos->obtenerConRelaciones($credito);
    }

    public function aprobar(Credito $credito): Credito
    {
        $this->exigirEstado($credito, EstadoCredito::Solicitado);

        return $this->creditos->actualizar($credito, ['estado' => EstadoCredito::Aprobado]);
    }

    /** Recién aquí se contabiliza el desembolso — antes de aprobar/desembolsar no ha salido dinero real. */
    public function desembolsar(Credito $credito): Credito
    {
        $this->exigirEstado($credito, EstadoCredito::Aprobado);

        $credito = $this->creditos->actualizar($credito, [
            'estado' => EstadoCredito::Desembolsado,
            'desembolsado_en' => now(),
        ]);
        $this->comprobantes->contabilizarDesembolsoCredito($credito);

        return $credito;
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

            if ($credito->estado === EstadoCredito::Desembolsado) {
                $this->reversarDesembolsoSiExiste($credito, 'Cancelación del crédito #'.$credito->id.' dentro de las 24 horas del desembolso');
            }

            return $this->creditos->actualizar($credito, ['estado' => EstadoCredito::Cancelado]);
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
            if ($credito->pagos()->exists()) {
                throw ValidationException::withMessages(['credito' => __('validation.custom.credito.tiene_pagos_registrados')]);
            }

            if ($credito->estado === EstadoCredito::Desembolsado) {
                $this->reversarDesembolsoSiExiste($credito, 'Eliminación del crédito #'.$credito->id);
            }

            $this->cuotas->eliminarTodasDeCredito($credito->id);

            return $this->creditos->eliminar($credito);
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
