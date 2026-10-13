<?php

namespace App\Gastos\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Causacion\Repositories\TipoGastoRepository;
use App\Causacion\Services\CausacionService;
use App\Contabilidad\Enums\CodigoDocumentoNegocio;
use App\Contabilidad\Enums\NaturalezaDocumento;
use App\Contabilidad\Models\AplicacionDocumento;
use App\Contabilidad\Services\ComprobanteService;
use App\Contabilidad\Services\DocumentoTerceroService;
use App\Contabilidad\Services\TipoDocumentoNegocioService;
use App\Core\Dinero\Dinero;
use App\Documentos\Services\ArchivoAdjuntoService;
use App\Exportacion\Enums\FormatoExportacion;
use App\Exportacion\Models\Exportacion;
use App\Exportacion\Services\ExportacionService;
use App\Gastos\Enums\EstadoGasto;
use App\Gastos\Exportaciones\FuenteGastos;
use App\Gastos\Models\Gasto;
use App\Gastos\Repositories\GastoReglaFiscalAplicadaRepository;
use App\Gastos\Repositories\GastoRepository;
use App\Terceros\Repositories\TerceroRepository;
use App\Usuarios\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GastoService
{
    public function __construct(
        protected GastoRepository $gastos,
        protected TipoGastoRepository $tiposGasto,
        protected GastoReglaFiscalAplicadaRepository $reglasAplicadas,
        protected TerceroRepository $terceros,
        protected CausacionService $causacion,
        protected ComprobanteService $comprobantes,
        protected DocumentoTerceroService $documentosTercero,
        protected TipoDocumentoNegocioService $tiposDocumentoNegocio,
        protected EmisorAuditoria $auditoria,
        protected ExportacionService $exportaciones,
        protected ArchivoAdjuntoService $archivos,
    ) {}

    public function solicitarExportacion(Usuario $usuario, ?string $busqueda, FormatoExportacion $formato): Exportacion
    {
        return $this->exportaciones->solicitar(new FuenteGastos((int) $usuario->empresa_id, $busqueda), $formato, $usuario);
    }

    public function listar(?string $busqueda, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->gastos->paginar($busqueda, max(1, min(100, $porPagina)), max(1, $pagina));
    }

    public function mostrar(Gasto $gasto): Gasto
    {
        return $this->gastos->obtenerConDetalle($gasto);
    }

    /**
     * `$soporte` es nulo solo desde la importación histórica (`ProcesadorGastos`): un gasto nuevo digitado por el
     * usuario siempre trae su soporte porque `CrearGastoRequest` lo exige antes de llegar aquí.
     */
    public function causar(array $datos, ?UploadedFile $soporte = null): Gasto
    {
        return DB::transaction(function () use ($datos, $soporte) {
            $tipoGasto = $this->tiposGasto->activo($datos['tipo_gasto_id']);
            if (! $tipoGasto) {
                throw ValidationException::withMessages([
                    'tipo_gasto_id' => __('validation.custom.tipo_gasto.inactivo_o_no_existe'),
                ]);
            }

            $tercero = isset($datos['tercero_id']) ? $this->terceros->buscarPorIdOFallar($datos['tercero_id']) : null;
            if ($tipoGasto->requiere_tercero && ! $tercero) {
                throw ValidationException::withMessages([
                    'tercero_id' => __('validation.custom.tipo_gasto.requiere_tercero'),
                ]);
            }

            $valorBruto = Dinero::desdePesos($datos['valor_bruto']);
            $resultado = $this->causacion->calcular($tipoGasto, $tercero, $datos['fecha'], $valorBruto, $datos['municipio_id'] ?? null);
            $parametrizacion = $resultado['parametrizacion'];
            $calculo = $resultado['calculo'];
            $retencionesPorTipo = $resultado['retenciones_por_tipo'];

            $gasto = $this->gastos->crear([
                'tipo_gasto_id' => $tipoGasto->id,
                'tercero_id' => $tercero?->id,
                'municipio_id' => $datos['municipio_id'] ?? null,
                'centro_costo_id' => $datos['centro_costo_id'],
                'fecha' => $datos['fecha'],
                'numero_documento' => $datos['numero_documento'],
                'concepto' => $datos['concepto'],
                'valor_bruto' => $valorBruto,
                'valor_iva' => $calculo['valor_iva'],
                'valor_retefuente' => $retencionesPorTipo['retefuente']['valor'],
                'valor_reteica' => $retencionesPorTipo['reteica']['valor'],
                'valor_reteiva' => $retencionesPorTipo['reteiva']['valor'],
                'valor_neto' => $calculo['valor_neto'],
                'parametrizacion_tipo_gasto_id' => $parametrizacion->id,
                'estado' => EstadoGasto::Causado,
                'creado_por' => Auth::id(),
            ]);

            if ($soporte) {
                $this->archivos->subir($gasto, $soporte);
            }

            $comprobante = $this->comprobantes->crearComprobante(
                $parametrizacion->tipo_comprobante_id,
                $datos['fecha'],
                $this->causacion->construirLineas($parametrizacion, $valorBruto, $calculo, $retencionesPorTipo, $datos['centro_costo_id']),
                $datos['concepto'],
                'gastos',
                $gasto->id,
                $datos['numero_documento'],
                $tercero?->id,
            );

            $documentoTercero = null;
            if ($tercero) {
                $this->tiposDocumentoNegocio->validarOperacion($gasto->empresa_id, CodigoDocumentoNegocio::ComprobanteCausacion, creaSaldo: true, cruzaSaldo: false);

                $documentoTercero = $this->documentosTercero->crearDocumento(
                    $tercero,
                    NaturalezaDocumento::PorPagar,
                    'gasto',
                    $datos['numero_documento'],
                    $datos['fecha'],
                    $datos['concepto'],
                    $calculo['valor_neto'],
                    'gastos',
                    $gasto->id,
                    planCuentaId: $parametrizacion->cuenta_cxp_id,
                    fechaVencimiento: $datos['fecha_vencimiento'] ?? null,
                );
            }

            $gasto = $this->gastos->actualizar($gasto, [
                'comprobante_id' => $comprobante->id,
                'documento_tercero_id' => $documentoTercero?->id,
            ]);

            $this->reglasAplicadas->crearVarias($gasto->id, $calculo['retenciones']);

            $this->auditoria->registrarCreacion(
                AuditoriaConstants::MODULO_GASTOS,
                "Gasto {$gasto->numero_documento} causado por {$valorBruto->aDecimalString()} (neto {$calculo['valor_neto']->aDecimalString()})",
                $gasto,
                accion: AccionAuditoria::Causar,
            );

            return $this->gastos->obtenerConDetalle($gasto);
        });
    }

    public function anular(Gasto $gasto): Gasto
    {
        return DB::transaction(function () use ($gasto) {
            if ($gasto->estado !== EstadoGasto::Causado) {
                throw ValidationException::withMessages([
                    'estado' => __('validation.custom.estado.transicion_invalida'),
                ]);
            }

            if ($gasto->documento_tercero_id) {
                $tieneCruces = AplicacionDocumento::where('documento_tercero_id', $gasto->documento_tercero_id)
                    ->whereNull('reversado_en')
                    ->exists();

                if ($tieneCruces) {
                    throw ValidationException::withMessages([
                        'gasto' => __('validation.custom.gasto.tiene_cruces_registrados'),
                    ]);
                }
            }

            $anteriores = $this->auditoria->instantanea($gasto);

            if ($gasto->comprobante) {
                $this->comprobantes->reversarComprobante($gasto->comprobante, 'Anulación del gasto #'.$gasto->id);
            }

            if ($gasto->documentoTercero) {
                $this->documentosTercero->anular($gasto->documentoTercero);
            }

            $this->gastos->actualizar($gasto, [
                'estado' => EstadoGasto::Anulado,
                'anulado_por' => Auth::id(),
                'anulado_en' => now(),
            ]);

            $this->gastos->eliminar($gasto);

            $this->auditoria->registrarModificacion(AuditoriaConstants::MODULO_GASTOS, "Gasto {$gasto->numero_documento} anulado", $gasto, $anteriores, accion: AccionAuditoria::Anular);

            return $gasto;
        });
    }
}
