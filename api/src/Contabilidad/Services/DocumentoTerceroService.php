<?php

namespace App\Contabilidad\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Contabilidad\Enums\EstadoDocumentoTercero;
use App\Contabilidad\Enums\NaturalezaDocumento;
use App\Contabilidad\Models\DocumentoTercero;
use App\Contabilidad\Models\PlanCuenta;
use App\Contabilidad\Repositories\AplicacionDocumentoRepository;
use App\Contabilidad\Repositories\DocumentoTerceroRepository;
use App\Core\Dinero\Dinero;
use App\Terceros\Models\Tercero;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Motor genérico de "documento con saldo por tercero" (facturas por pagar, cuentas
 * por cobrar, notas de ajuste...). Los módulos que causan documentos (Facturas,
 * Ingresos...) llaman `crearDocumento()`; los que aplican dinero contra ellos
 * (Egresos, Ingresos...) llaman `aplicarValor()`. Ninguno de los dos conoce el otro
 * módulo — solo conocen este servicio, evitando reimplementar la misma regla de
 * "seleccionar documento(s) pendientes, aplicar valor, actualizar saldo" en cada uno.
 */
class DocumentoTerceroService
{
    public function __construct(
        protected DocumentoTerceroRepository $documentos,
        protected AplicacionDocumentoRepository $aplicaciones,
        protected EmisorAuditoria $auditoria,
    ) {}

    /** Anula (soft delete) un documento que nunca llegó a tener aplicaciones — el llamador ya validó eso. */
    public function anular(DocumentoTercero $documento): bool
    {
        $anulado = $this->documentos->eliminar($documento);

        $this->auditoria->registrarEliminacion(AuditoriaConstants::MODULO_CONTABILIDAD, "Documento {$documento->numero_documento} anulado", $documento, accion: AccionAuditoria::Anular);

        return $anulado;
    }

    public function pendientesPorTercero(Tercero $tercero, NaturalezaDocumento $naturaleza)
    {
        return $this->documentos->pendientesPorTercero($tercero, $naturaleza);
    }

    /** Lectura simple (sin bloqueo) de varios documentos por id — para armar de antemano las líneas de un comprobante que los va a cruzar. */
    public function buscarVariosPorId(array $ids): Collection
    {
        return $this->documentos->porIds($ids);
    }

    public function bloquearPorId(int $id): DocumentoTercero
    {
        return $this->documentos->bloquearPorId($id);
    }

    public function buscarPorNumero(int $terceroId, NaturalezaDocumento $naturaleza, string $numeroDocumento): ?DocumentoTercero
    {
        return $this->documentos->buscarPorNumero($terceroId, $naturaleza, $numeroDocumento);
    }

    /** @return Collection<int, DocumentoTercero> */
    public function creadosPorOrigen(string $moduloOrigen, int $origenId): Collection
    {
        return $this->documentos->creadosPorOrigen($moduloOrigen, $origenId);
    }

    public function crearDocumento(
        Tercero $tercero,
        NaturalezaDocumento $naturaleza,
        string $tipoDocumento,
        string $numeroDocumento,
        string $fecha,
        string $concepto,
        Dinero $valor,
        string $moduloOrigen,
        int $origenId,
        ?DocumentoTercero $documentoOriginal = null,
        ?int $planCuentaId = null,
        ?string $fechaVencimiento = null,
    ): DocumentoTercero {
        if ($planCuentaId && empty($fechaVencimiento) && PlanCuenta::query()->find($planCuentaId)?->exige_vencimiento) {
            throw ValidationException::withMessages([
                'documento' => __('validation.custom.documento.vencimiento_requerido'),
            ]);
        }

        $documento = $this->documentos->crear([
            'tercero_id' => $tercero->id,
            'naturaleza' => $naturaleza,
            'plan_cuenta_id' => $planCuentaId,
            'tipo_documento' => $tipoDocumento,
            'numero_documento' => $numeroDocumento,
            'fecha' => $fecha,
            'fecha_vencimiento' => $fechaVencimiento,
            'concepto' => $concepto,
            'valor_original' => $valor,
            'saldo' => $valor,
            'estado' => EstadoDocumentoTercero::Pendiente,
            'modulo_origen' => $moduloOrigen,
            'origen_id' => $origenId,
            'documento_original_id' => $documentoOriginal?->id,
        ]);

        $this->auditoria->registrarCreacion(AuditoriaConstants::MODULO_CONTABILIDAD, "Documento {$numeroDocumento} ({$naturaleza->value}) creado por {$valor->aDecimalString()}", $documento);

        return $documento;
    }

    /**
     * Aplica valor a los documentos pendientes que el usuario seleccionó
     * explícitamente (`$aplicaciones`: `[['documento_tercero_id' => int, 'valor' =>
     * float], ...]`), total o parcial por documento. A diferencia de
     * `PagoService::registrarPago` (que reparte automáticamente "más antiguo
     * primero" y descarta el sobrante), aquí no hay reparto automático ni sobrante
     * silencioso: cada valor es explícito y nunca puede exceder el saldo del
     * documento, porque estos documentos representan un pasivo/activo real.
     *
     * @param  array<int, array{documento_tercero_id: int, valor: Dinero|int|float|string}>  $aplicaciones
     * @return array{documentos: Collection<int, DocumentoTercero>, total_aplicado: Dinero}
     */
    public function aplicarValor(
        Tercero $tercero,
        NaturalezaDocumento $naturaleza,
        array $aplicaciones,
        string $moduloAplicacion,
        int $aplicacionId,
        ?string $fecha = null,
    ): array {
        if (empty($aplicaciones)) {
            throw ValidationException::withMessages([
                'aplicaciones' => __('validation.custom.documento.sin_aplicaciones'),
            ]);
        }

        $fecha ??= now()->toDateString();

        return DB::transaction(function () use ($tercero, $naturaleza, $aplicaciones, $moduloAplicacion, $aplicacionId, $fecha) {
            $ids = array_column($aplicaciones, 'documento_tercero_id');
            $documentosBloqueados = $this->documentos
                ->bloquearPendientesPorIds($ids, $tercero->id, $naturaleza)
                ->keyBy('id');

            if ($documentosBloqueados->count() !== count(array_unique($ids))) {
                throw ValidationException::withMessages([
                    'aplicaciones' => __('validation.custom.documento.no_pendiente_o_no_pertenece'),
                ]);
            }

            $totalAplicado = Dinero::cero();
            $documentosActualizados = collect();

            foreach ($aplicaciones as $aplicacion) {
                $documento = $documentosBloqueados[$aplicacion['documento_tercero_id']];
                $valor = $aplicacion['valor'] instanceof Dinero ? $aplicacion['valor'] : Dinero::desdePesos($aplicacion['valor']);

                if (! $valor->esPositivo() || $valor->mayorQue($documento->saldo)) {
                    throw ValidationException::withMessages([
                        'aplicaciones' => __('validation.custom.documento.saldo_insuficiente'),
                    ]);
                }

                $this->aplicaciones->crear([
                    'documento_tercero_id' => $documento->id,
                    'modulo_aplicacion' => $moduloAplicacion,
                    'aplicacion_id' => $aplicacionId,
                    'valor_aplicado' => $valor,
                    'fecha' => $fecha,
                ]);

                $nuevoSaldo = $documento->saldo->restar($valor);
                $anteriores = $this->auditoria->instantanea($documento);

                $this->documentos->actualizar($documento, [
                    'saldo' => $nuevoSaldo,
                    'estado' => $this->estadoPorSaldo($nuevoSaldo, $documento->valor_original),
                ]);

                $this->auditoria->registrarModificacion(
                    AuditoriaConstants::MODULO_CONTABILIDAD,
                    "Documento {$documento->numero_documento}: aplicados {$valor->aDecimalString()}",
                    $documento,
                    $anteriores,
                    adicionales: ['valor_aplicado' => $valor, 'modulo_aplicacion' => $moduloAplicacion, 'aplicacion_id' => $aplicacionId],
                );

                $totalAplicado = $totalAplicado->sumar($valor);
                $documentosActualizados->push($documento);
            }

            return ['documentos' => $documentosActualizados, 'total_aplicado' => $totalAplicado];
        });
    }

    /**
     * Aumenta el saldo de un documento ya existente: nota débito sobre una CxC (carga adicional, p. ej. intereses
     * de mora, que no corrige el valor original sino que suma deuda nueva) o reversa de un cobro/egreso. Lo
     * opuesto de `aplicarValor()`. A diferencia de esa, no está acotada por `valor_original`: una nota débito puede
     * dejar el saldo por encima de él a propósito.
     */
    public function incrementarValor(
        DocumentoTercero $documento,
        Dinero $valor,
        string $moduloAplicacion,
        int $aplicacionId,
        ?string $fecha = null,
    ): DocumentoTercero {
        if (! $valor->esPositivo()) {
            throw ValidationException::withMessages([
                'valor' => __('validation.custom.documento.saldo_insuficiente'),
            ]);
        }

        $fecha ??= now()->toDateString();

        return DB::transaction(function () use ($documento, $valor, $moduloAplicacion, $aplicacionId, $fecha) {
            $bloqueado = $this->documentos->bloquearPorId($documento->id);

            $nuevoSaldo = $bloqueado->saldo->sumar($valor);

            // Convención: valor_aplicado negativo = aumenta el saldo (nota débito, reversa de cobro/egreso).
            $this->aplicaciones->crear([
                'documento_tercero_id' => $bloqueado->id,
                'modulo_aplicacion' => $moduloAplicacion,
                'aplicacion_id' => $aplicacionId,
                'valor_aplicado' => $valor->negar(),
                'fecha' => $fecha,
            ]);

            $anteriores = $this->auditoria->instantanea($bloqueado);

            $this->documentos->actualizar($bloqueado, [
                'saldo' => $nuevoSaldo,
                'estado' => $this->estadoPorSaldo($nuevoSaldo, $bloqueado->valor_original),
            ]);

            $this->auditoria->registrarModificacion(
                AuditoriaConstants::MODULO_CONTABILIDAD,
                "Documento {$bloqueado->numero_documento}: incrementados {$valor->aDecimalString()}",
                $bloqueado,
                $anteriores,
                adicionales: ['valor_incrementado' => $valor, 'modulo_aplicacion' => $moduloAplicacion, 'aplicacion_id' => $aplicacionId],
            );

            return $bloqueado;
        });
    }

    private function estadoPorSaldo(Dinero $saldo, Dinero $valorOriginal): EstadoDocumentoTercero
    {
        if (! $saldo->esPositivo()) {
            return EstadoDocumentoTercero::Cancelado;
        }

        return $saldo->menorQue($valorOriginal) ? EstadoDocumentoTercero::AbonadoParcial : EstadoDocumentoTercero::Pendiente;
    }
}
