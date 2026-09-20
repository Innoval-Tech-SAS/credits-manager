<?php

namespace App\Contabilidad\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Contabilidad\Enums\EstadoDocumentoTercero;
use App\Contabilidad\Enums\NaturalezaDocumento;
use App\Contabilidad\Models\DocumentoTercero;
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
    ): DocumentoTercero {
        $documento = $this->documentos->crear([
            'tercero_id' => $tercero->id,
            'naturaleza' => $naturaleza,
            'tipo_documento' => $tipoDocumento,
            'numero_documento' => $numeroDocumento,
            'fecha' => $fecha,
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
                    'estado' => $nuevoSaldo->esPositivo() ? EstadoDocumentoTercero::Pendiente : EstadoDocumentoTercero::Cancelado,
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
}
