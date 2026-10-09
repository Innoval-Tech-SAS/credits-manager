<?php

namespace App\Egresos\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Services\EmisorAuditoria;
use App\Contabilidad\Enums\NaturalezaDocumento;
use App\Contabilidad\Services\ComprobanteService;
use App\Contabilidad\Services\DocumentoTerceroService;
use App\Core\Consecutivos\Enums\TipoDocumentoConsecutivo;
use App\Core\Consecutivos\Services\ConsecutivoDocumentoService;
use App\Core\Dinero\Dinero;
use App\Egresos\Enums\MedioPagoEgreso;
use App\Egresos\Models\Egreso;
use App\Egresos\Repositories\EgresoRepository;
use App\Terceros\Repositories\TerceroRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pago de documentos por pagar (Gastos/proveedores). Agrupa las aplicaciones por la cuenta PUC de cada documento
 * para que un mismo egreso pueda pagar documentos de cuentas distintas (p. ej. 2205 y 2335) con un solo comprobante.
 */
class EgresoService
{
    public function __construct(
        protected EgresoRepository $egresos,
        protected TerceroRepository $terceros,
        protected DocumentoTerceroService $documentosTercero,
        protected ComprobanteService $comprobantes,
        protected ConsecutivoDocumentoService $consecutivos,
        protected EmisorAuditoria $auditoria,
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
     * @param  array{tercero_id: int, fecha: string, medio_pago: string, cuenta_caja_id: int, tipo_comprobante_id: int, concepto: string, aplicaciones: list<array{documento_tercero_id: int, valor: string}>}  $datos
     */
    public function registrar(array $datos): Egreso
    {
        return DB::transaction(function () use ($datos) {
            $tercero = $this->terceros->buscarPorIdOFallar($datos['tercero_id']);

            $ids = array_column($datos['aplicaciones'], 'documento_tercero_id');
            $documentos = $this->documentosTercero->buscarVariosPorId($ids)->keyBy('id');

            if ($documentos->count() !== count(array_unique($ids))) {
                throw ValidationException::withMessages([
                    'aplicaciones' => __('validation.custom.documento.no_pendiente_o_no_pertenece'),
                ]);
            }

            $lineasPorCuenta = [];
            $valorTotal = Dinero::cero();

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
                $lineasPorCuenta[$documento->plan_cuenta_id] = ($lineasPorCuenta[$documento->plan_cuenta_id] ?? Dinero::cero())->sumar($valor);
                $valorTotal = $valorTotal->sumar($valor);
            }

            $consecutivo = $this->consecutivos->siguiente(TipoDocumentoConsecutivo::Egreso);
            $numero = trim(($consecutivo['prefijo'] ? $consecutivo['prefijo'].' ' : '').$consecutivo['numero']);

            $egreso = $this->egresos->crear([
                'tercero_id' => $tercero->id,
                'fecha' => $datos['fecha'],
                'consecutivo' => $consecutivo['numero'],
                'numero' => $numero,
                'valor' => $valorTotal,
                'medio_pago' => MedioPagoEgreso::from($datos['medio_pago']),
                'cuenta_caja_id' => $datos['cuenta_caja_id'],
                'concepto' => $datos['concepto'],
                'creado_por' => Auth::id(),
            ]);

            $lineasComprobante = [];

            foreach ($lineasPorCuenta as $cuentaId => $valorCuenta) {
                $lineasComprobante[] = ['cuenta_id' => $cuentaId, 'debito' => $valorCuenta, 'credito' => 0];
            }

            $lineasComprobante[] = ['cuenta_id' => $datos['cuenta_caja_id'], 'debito' => 0, 'credito' => $valorTotal];

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
}
