<?php

namespace App\Egresos\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Contabilidad\Enums\CodigoDocumentoNegocio;
use App\Contabilidad\Enums\NaturalezaDocumento;
use App\Contabilidad\Repositories\AplicacionDocumentoRepository;
use App\Contabilidad\Services\ComprobanteService;
use App\Contabilidad\Services\DocumentoTerceroService;
use App\Contabilidad\Services\TipoDocumentoNegocioService;
use App\Core\Consecutivos\Enums\TipoDocumentoConsecutivo;
use App\Core\Consecutivos\Services\ConsecutivoDocumentoService;
use App\Core\Dinero\Dinero;
use App\Documentos\Services\ArchivoAdjuntoService;
use App\Egresos\Enums\MedioPagoEgreso;
use App\Egresos\Models\Egreso;
use App\Egresos\Models\ReversaEgreso;
use App\Egresos\Repositories\EgresoRepository;
use App\Egresos\Repositories\ReversaEgresoRepository;
use App\Terceros\Repositories\TerceroRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pago de documentos por pagar (Gastos/proveedores). Cada documento aplicado genera su propia línea de débito
 * (con su documento cruce y vencimiento), aunque dos documentos compartan la misma cuenta PUC: nunca se suman
 * en una sola línea, para no perder la trazabilidad factura-por-factura del comprobante.
 */
class EgresoService
{
    public function __construct(
        protected EgresoRepository $egresos,
        protected TerceroRepository $terceros,
        protected DocumentoTerceroService $documentosTercero,
        protected TipoDocumentoNegocioService $tiposDocumentoNegocio,
        protected ComprobanteService $comprobantes,
        protected ConsecutivoDocumentoService $consecutivos,
        protected EmisorAuditoria $auditoria,
        protected ArchivoAdjuntoService $archivos,
        protected ReversaEgresoRepository $reversas,
        protected AplicacionDocumentoRepository $aplicacionesDocumento,
    ) {}

    public function listar(?int $terceroId, ?string $desde, ?string $hasta, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->egresos->paginarFiltrado(
            ['tercero_id' => $terceroId, 'desde' => $desde, 'hasta' => $hasta],
            max(1, min(100, $porPagina)),
            max(1, $pagina),
        );
    }

    /**
     * @param  array{tercero_id: int, fecha: string, medio_pago: string, cuenta_caja_id: int, cuenta_descuento_id?: int|null, tipo_comprobante_id: int, concepto: string, aplicaciones: list<array{documento_tercero_id: int, valor: string, descuento?: string|null}>}  $datos
     */
    public function registrar(array $datos, UploadedFile $soporte): Egreso
    {
        return DB::transaction(function () use ($datos, $soporte) {
            $tercero = $this->terceros->buscarPorIdOFallar($datos['tercero_id']);

            $ids = array_column($datos['aplicaciones'], 'documento_tercero_id');
            $documentos = $this->documentosTercero->buscarVariosPorId($ids)->keyBy('id');

            if ($documentos->count() !== count(array_unique($ids))) {
                throw ValidationException::withMessages([
                    'aplicaciones' => __('validation.custom.documento.no_pendiente_o_no_pertenece'),
                ]);
            }

            $lineasDocumentos = [];
            $valorTotal = Dinero::cero();
            $totalDescuento = Dinero::cero();

            foreach ($datos['aplicaciones'] as $aplicacion) {
                $documento = $documentos[$aplicacion['documento_tercero_id']];

                if ($documento->naturaleza !== NaturalezaDocumento::PorPagar || $documento->tercero_id !== $tercero->id) {
                    throw ValidationException::withMessages([
                        'aplicaciones' => __('validation.custom.documento.no_pendiente_o_no_pertenece'),
                    ]);
                }

                if ($documento->plan_cuenta_id === null) {
                    throw ValidationException::withMessages([
                        'aplicaciones' => __('validation.custom.documento.sin_cuenta_contable'),
                    ]);
                }

                $valor = Dinero::desdePesos($aplicacion['valor']);
                $descuento = Dinero::desdePesos($aplicacion['descuento'] ?? '0');

                if ($descuento->mayorOIgualQue($valor)) {
                    throw ValidationException::withMessages([
                        'aplicaciones' => __('validation.custom.egreso.descuento_mayor_o_igual_al_valor'),
                    ]);
                }

                $lineasDocumentos[] = [
                    'cuenta_id' => $documento->plan_cuenta_id,
                    'documento_cruce' => $documento->numero_documento,
                    'fecha_vencimiento' => $documento->fecha_vencimiento?->toDateString(),
                    'debito' => $valor,
                    'credito' => 0,
                ];
                $valorTotal = $valorTotal->sumar($valor);
                $totalDescuento = $totalDescuento->sumar($descuento);
            }

            if ($totalDescuento->esPositivo() && empty($datos['cuenta_descuento_id'])) {
                throw ValidationException::withMessages([
                    'cuenta_descuento_id' => __('validation.custom.egreso.cuenta_descuento_requerida'),
                ]);
            }

            $valorCaja = $valorTotal->restar($totalDescuento);

            $consecutivo = $this->consecutivos->siguiente(TipoDocumentoConsecutivo::Egreso);
            $numero = trim(($consecutivo['prefijo'] ? $consecutivo['prefijo'].' ' : '').$consecutivo['numero']);

            $egreso = $this->egresos->crear([
                'tercero_id' => $tercero->id,
                'fecha' => $datos['fecha'],
                'consecutivo' => $consecutivo['numero'],
                'numero' => $numero,
                'valor' => $valorTotal,
                'valor_descuento' => $totalDescuento,
                'medio_pago' => MedioPagoEgreso::from($datos['medio_pago']),
                'cuenta_caja_id' => $datos['cuenta_caja_id'],
                'cuenta_descuento_id' => $datos['cuenta_descuento_id'] ?? null,
                'concepto' => $datos['concepto'],
                'creado_por' => Auth::id(),
            ]);

            $this->archivos->subir($egreso, $soporte);

            $lineasComprobante = $lineasDocumentos;

            $lineasComprobante[] = ['cuenta_id' => $datos['cuenta_caja_id'], 'debito' => 0, 'credito' => $valorCaja];

            if ($totalDescuento->esPositivo()) {
                $lineasComprobante[] = ['cuenta_id' => $datos['cuenta_descuento_id'], 'debito' => 0, 'credito' => $totalDescuento];
            }

            $comprobante = $this->comprobantes->crearComprobante(
                $datos['tipo_comprobante_id'],
                $datos['fecha'],
                $lineasComprobante,
                $datos['concepto'],
                'egresos',
                $egreso->id,
                $numero,
                $tercero->id,
            );

            $egreso = $this->egresos->actualizar($egreso, ['comprobante_id' => $comprobante->id]);

            $this->tiposDocumentoNegocio->validarOperacion($tercero->empresa_id, CodigoDocumentoNegocio::ComprobanteEgreso, creaSaldo: false, cruzaSaldo: true);

            $this->documentosTercero->aplicarValor(
                $tercero,
                NaturalezaDocumento::PorPagar,
                $datos['aplicaciones'],
                'egresos',
                $egreso->id,
                $datos['fecha'],
            );

            $this->auditoria->registrarCreacion(
                AuditoriaConstants::MODULO_EGRESOS,
                "Egreso {$numero} por {$valorTotal->aDecimalString()} a {$tercero->nombre}",
                $egreso,
            );

            return $this->egresos->buscarPorIdOFallar($egreso->id);
        });
    }

    /**
     * Reversa un egreso mediante movimiento compensatorio: el egreso no se toca (sigue inmutable); los documentos
     * por pagar que había cruzado recuperan el saldo abonado y el comprobante se reversa con un asiento enlazado.
     */
    public function reversar(Egreso $egreso, string $motivo, ?int $usuarioId): ReversaEgreso
    {
        return DB::transaction(function () use ($egreso, $motivo, $usuarioId) {
            if ($this->reversas->existePara($egreso->id)) {
                throw ValidationException::withMessages([
                    'egreso' => __('validation.custom.egreso.ya_reversado'),
                ]);
            }

            $vigentes = $this->aplicacionesDocumento->vigentesDe('egresos', $egreso->id);
            $documentos = $this->documentosTercero
                ->buscarVariosPorId($vigentes->pluck('documento_tercero_id')->unique()->all())
                ->keyBy('id');

            foreach ($vigentes as $aplicacion) {
                $documento = $documentos[$aplicacion->documento_tercero_id];
                $this->documentosTercero->incrementarValor($documento, $aplicacion->valor_aplicado, 'egresos_reversa', $egreso->id);
            }

            $this->aplicacionesDocumento->marcarReversadas($vigentes->pluck('id')->all());

            $comprobante = $this->comprobantes->buscarContabilizadoPorOrigen('egresos', $egreso->id);
            $reverso = $comprobante ? $this->comprobantes->reversarComprobante($comprobante, $motivo) : null;

            $reversa = $this->reversas->crear([
                'egreso_id' => $egreso->id,
                'comprobante_reverso_id' => $reverso?->id,
                'fecha' => now('America/Bogota')->toDateString(),
                'valor' => $egreso->valor,
                'motivo' => $motivo,
                'registrado_por' => $usuarioId,
            ]);

            $this->auditoria->registrar(
                AccionAuditoria::Reversar,
                AuditoriaConstants::MODULO_EGRESOS,
                "Egreso {$egreso->numero} de {$egreso->valor->aDecimalString()} reversado",
                'egreso',
                $egreso->id,
                nuevos: [
                    'reversa_id' => $reversa->id,
                    'comprobante_reverso_id' => $reverso?->id,
                    'documentos_afectados' => $documentos->keys()->all(),
                ],
                motivo: $motivo,
            );

            return $reversa;
        });
    }
}
