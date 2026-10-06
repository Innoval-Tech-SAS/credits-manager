<?php

namespace App\Gastos\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Contabilidad\Enums\NaturalezaDocumento;
use App\Contabilidad\Models\AplicacionDocumento;
use App\Contabilidad\Services\ComprobanteService;
use App\Contabilidad\Services\DocumentoTerceroService;
use App\Core\Dinero\Dinero;
use App\Exportacion\Enums\FormatoExportacion;
use App\Exportacion\Models\Exportacion;
use App\Exportacion\Services\ExportacionService;
use App\Gastos\Enums\EstadoGasto;
use App\Gastos\Exportaciones\FuenteGastos;
use App\Gastos\Models\Gasto;
use App\Gastos\Models\ParametrizacionTipoGasto;
use App\Gastos\Repositories\GastoReglaFiscalAplicadaRepository;
use App\Gastos\Repositories\GastoRepository;
use App\Gastos\Repositories\ParametrizacionTipoGastoRepository;
use App\Gastos\Repositories\TarifaReteIcaMunicipioRepository;
use App\Gastos\Repositories\TipoGastoRepository;
use App\Gastos\Repositories\ValorUvtRepository;
use App\Terceros\Repositories\TerceroRepository;
use App\Usuarios\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GastoService
{
    public function __construct(
        protected GastoRepository $gastos,
        protected TipoGastoRepository $tiposGasto,
        protected ParametrizacionTipoGastoRepository $parametrizaciones,
        protected ValorUvtRepository $valoresUvt,
        protected TarifaReteIcaMunicipioRepository $tarifasReteIca,
        protected GastoReglaFiscalAplicadaRepository $reglasAplicadas,
        protected TerceroRepository $terceros,
        protected MotorReglasFiscalesService $motor,
        protected ComprobanteService $comprobantes,
        protected DocumentoTerceroService $documentosTercero,
        protected EmisorAuditoria $auditoria,
        protected ExportacionService $exportaciones,
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

    public function causar(array $datos): Gasto
    {
        return DB::transaction(function () use ($datos) {
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

            $parametrizacion = $this->parametrizaciones->vigenteParaFecha($tipoGasto->id, $datos['fecha']);
            if (! $parametrizacion) {
                throw ValidationException::withMessages([
                    'tipo_gasto_id' => __('validation.custom.parametrizacion.no_vigente'),
                ]);
            }

            $uvt = $this->valoresUvt->paraAnio((int) date('Y', strtotime($datos['fecha'])));
            if (! $uvt) {
                throw ValidationException::withMessages([
                    'fecha' => __('validation.custom.valor_uvt.no_configurado'),
                ]);
            }

            $tarifaReteIca = null;
            if ($parametrizacion->aplica_reteica) {
                if (! isset($datos['municipio_id']) || ! $tercero?->actividad_economica_id) {
                    throw ValidationException::withMessages([
                        'municipio_id' => __('validation.custom.tarifa_reteica.no_vigente'),
                    ]);
                }

                $tarifaReteIca = $this->tarifasReteIca->vigenteParaFecha(
                    $datos['municipio_id'],
                    $tercero->actividad_economica_id,
                    $datos['fecha'],
                );
            }

            $valorBruto = Dinero::desdePesos($datos['valor_bruto']);
            $calculo = $this->motor->evaluar($valorBruto, $parametrizacion, $tercero, $tarifaReteIca, $uvt);
            $retencionesPorTipo = collect($calculo['retenciones'])->keyBy(fn (array $r) => $r['tipo']->value);

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

            $comprobante = $this->comprobantes->crearComprobante(
                $parametrizacion->tipo_comprobante_id,
                $datos['fecha'],
                $this->construirLineasComprobante($parametrizacion, $valorBruto, $calculo, $retencionesPorTipo, $datos['centro_costo_id']),
                $datos['concepto'],
                'gastos',
                $gasto->id,
                $datos['numero_documento'],
                $tercero?->id,
            );

            $documentoTercero = null;
            if ($tercero) {
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
                // TODO(Fase B - Egresos): una vez exista `aplicaciones_documento.reversado_en`,
                // filtrar aquí `whereNull('reversado_en')` para permitir anular un gasto cuyos
                // cruces ya fueron reversados (RF-14).
                $tieneCruces = AplicacionDocumento::where('documento_tercero_id', $gasto->documento_tercero_id)->exists();

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

    /** @param  Collection<string, array>  $retencionesPorTipo */
    private function construirLineasComprobante(ParametrizacionTipoGasto $parametrizacion, Dinero $valorBruto, array $calculo, Collection $retencionesPorTipo, int $centroCostoId): array
    {
        $lineas = [
            ['cuenta_id' => $parametrizacion->cuenta_gasto_id, 'debito' => $valorBruto, 'credito' => 0, 'centro_costo_id' => $centroCostoId],
        ];

        if ($calculo['valor_iva']->esPositivo()) {
            $lineas[] = ['cuenta_id' => $parametrizacion->cuenta_iva_id, 'debito' => $calculo['valor_iva'], 'credito' => 0];
        }

        $lineas[] = ['cuenta_id' => $parametrizacion->cuenta_cxp_id, 'debito' => 0, 'credito' => $calculo['valor_neto']];

        $cuentasPorTipo = [
            'retefuente' => $parametrizacion->cuenta_retefuente_id,
            'reteica' => $parametrizacion->cuenta_reteica_id,
            'reteiva' => $parametrizacion->cuenta_reteiva_id,
        ];

        foreach ($cuentasPorTipo as $tipo => $cuentaId) {
            $retencion = $retencionesPorTipo[$tipo];
            if ($retencion['incluida'] && $retencion['valor']->esPositivo()) {
                $lineas[] = ['cuenta_id' => $cuentaId, 'debito' => 0, 'credito' => $retencion['valor']];
            }
        }

        return $lineas;
    }
}
