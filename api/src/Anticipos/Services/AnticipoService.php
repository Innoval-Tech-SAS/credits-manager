<?php

namespace App\Anticipos\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Contabilidad\Enums\NaturalezaDocumento;
use App\Contabilidad\Models\DocumentoTercero;
use App\Contabilidad\Services\ComprobanteService;
use App\Contabilidad\Services\DocumentoTerceroService;
use App\Core\Dinero\Dinero;
use App\Terceros\Repositories\TerceroRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Anticipos a proveedores o de clientes: dinero que se mueve antes de que exista la factura. Se modelan como un
 * `DocumentoTercero` más (naturaleza `anticipo_proveedor`/`anticipo_cliente`), sin tabla propia — mismo motor de
 * saldo y aplicaciones que ya usan facturas y gastos. `cruzar()` es lo distinto: en vez de recibir dinero, consume
 * el saldo del anticipo contra el saldo de una PorPagar/PorCobrar real, con su propio asiento de cruce.
 */
class AnticipoService
{
    public function __construct(
        protected TerceroRepository $terceros,
        protected DocumentoTerceroService $documentosTercero,
        protected ComprobanteService $comprobantes,
        protected EmisorAuditoria $auditoria,
    ) {}

    /** @param array{tercero_id: int, fecha: string, numero_documento: string, concepto: string, valor: string, cuenta_anticipo_id: int, cuenta_caja_id: int, tipo_comprobante_id: int} $datos */
    public function registrarAnticipoProveedor(array $datos): DocumentoTercero
    {
        return $this->registrar(NaturalezaDocumento::AnticipoProveedor, $datos);
    }

    /** @param array{tercero_id: int, fecha: string, numero_documento: string, concepto: string, valor: string, cuenta_anticipo_id: int, cuenta_caja_id: int, tipo_comprobante_id: int} $datos */
    public function registrarAnticipoCliente(array $datos): DocumentoTercero
    {
        return $this->registrar(NaturalezaDocumento::AnticipoCliente, $datos);
    }

    private function registrar(NaturalezaDocumento $naturaleza, array $datos): DocumentoTercero
    {
        return DB::transaction(function () use ($naturaleza, $datos) {
            $tercero = $this->terceros->buscarPorIdOFallar($datos['tercero_id']);
            $valor = Dinero::desdePesos($datos['valor']);

            // Proveedor: débito al anticipo (activo que entra), crédito a la caja que sale. Cliente: al revés —
            // entra dinero (débito caja), queda un pasivo con el cliente (crédito anticipo).
            $esProveedor = $naturaleza === NaturalezaDocumento::AnticipoProveedor;
            $lineaAnticipo = ['cuenta_id' => $datos['cuenta_anticipo_id'], 'tercero_id' => $tercero->id, 'documento_cruce_propio' => true];
            $lineaCaja = ['cuenta_id' => $datos['cuenta_caja_id']];

            $comprobante = $this->comprobantes->crearComprobante(
                $datos['tipo_comprobante_id'],
                $datos['fecha'],
                [
                    [...($esProveedor ? $lineaAnticipo : $lineaCaja), 'debito' => $valor, 'credito' => 0],
                    [...($esProveedor ? $lineaCaja : $lineaAnticipo), 'debito' => 0, 'credito' => $valor],
                ],
                $datos['concepto'],
                null,
                null,
                $datos['numero_documento'],
                $tercero->id,
            );

            // El anticipo se cruza por el número de su comprobante; `numero_documento` queda como documento soporte.
            $documento = $this->documentosTercero->crearDocumento(
                $tercero,
                $naturaleza,
                'anticipo',
                $comprobante->numero,
                $datos['fecha'],
                $datos['concepto'],
                $valor,
                'anticipos',
                $comprobante->id,
                planCuentaId: $datos['cuenta_anticipo_id'],
                documentoSoporte: $datos['numero_documento'],
            );

            $this->auditoria->registrarCreacion(
                AuditoriaConstants::MODULO_CONTABILIDAD,
                "Anticipo {$documento->numero_documento} registrado por {$valor->aDecimalString()}",
                $documento,
            );

            return $documento;
        });
    }

    /**
     * Cruza un anticipo contra el documento real que finalmente llegó: un asiento (débito a la cuenta real, crédito
     * al anticipo — o al revés del lado cliente) y reduce el saldo de ambos documentos por el mismo valor.
     *
     * @param  array{anticipo_id: int, documento_id: int, valor: string, fecha: string, tipo_comprobante_id: int}  $datos
     * @return array{anticipo: DocumentoTercero, documento: DocumentoTercero}
     */
    public function cruzar(array $datos): array
    {
        return DB::transaction(function () use ($datos) {
            // Orden fijo (el id menor primero) para que dos cruces concurrentes sobre los mismos documentos no se
            // bloqueen en espejo.
            $ids = [$datos['anticipo_id'], $datos['documento_id']];
            sort($ids);
            $bloqueados = collect($ids)->mapWithKeys(fn (int $id) => [$id => $this->documentosTercero->bloquearPorId($id)]);

            $anticipo = $bloqueados[$datos['anticipo_id']];
            $documento = $bloqueados[$datos['documento_id']];

            $parValido = ($anticipo->naturaleza === NaturalezaDocumento::AnticipoProveedor && $documento->naturaleza === NaturalezaDocumento::PorPagar)
                || ($anticipo->naturaleza === NaturalezaDocumento::AnticipoCliente && $documento->naturaleza === NaturalezaDocumento::PorCobrar);

            if (! $parValido || $anticipo->tercero_id !== $documento->tercero_id) {
                throw ValidationException::withMessages(['documento_id' => __('validation.custom.anticipo.cruce_invalido')]);
            }

            if (! $anticipo->plan_cuenta_id || ! $documento->plan_cuenta_id) {
                throw ValidationException::withMessages(['documento_id' => __('validation.custom.documento.sin_cuenta_contable')]);
            }

            $valor = Dinero::desdePesos($datos['valor']);

            if (! $valor->esPositivo() || $valor->mayorQue($anticipo->saldo) || $valor->mayorQue($documento->saldo)) {
                throw ValidationException::withMessages(['valor' => __('validation.custom.documento.saldo_insuficiente')]);
            }

            $esProveedor = $anticipo->naturaleza === NaturalezaDocumento::AnticipoProveedor;
            // CxP: débito a la cuenta real (baja lo que debemos), crédito al anticipo (se consume el saldo a favor).
            // CxC: al revés — débito al anticipo (se consume el pasivo con el cliente), crédito a la cuenta real.
            $lineaDocumento = ['cuenta_id' => $documento->plan_cuenta_id, 'documento_cruce' => $documento->numero_documento];
            $lineaAnticipo = ['cuenta_id' => $anticipo->plan_cuenta_id, 'documento_cruce' => $anticipo->numero_documento];

            $comprobante = $this->comprobantes->crearComprobante(
                $datos['tipo_comprobante_id'],
                $datos['fecha'],
                [
                    [...($esProveedor ? $lineaDocumento : $lineaAnticipo), 'debito' => $valor, 'credito' => 0],
                    [...($esProveedor ? $lineaAnticipo : $lineaDocumento), 'debito' => 0, 'credito' => $valor],
                ],
                "Cruce anticipo {$anticipo->numero_documento} con {$documento->numero_documento}",
                'anticipos_cruce',
                $anticipo->id,
                null,
                $anticipo->tercero_id,
            );

            $this->documentosTercero->aplicarValor(
                $anticipo->tercero,
                $anticipo->naturaleza,
                [['documento_tercero_id' => $anticipo->id, 'valor' => $valor]],
                'anticipos_cruce',
                $comprobante->id,
                $datos['fecha'],
            );

            $this->documentosTercero->aplicarValor(
                $documento->tercero,
                $documento->naturaleza,
                [['documento_tercero_id' => $documento->id, 'valor' => $valor]],
                'anticipos_cruce',
                $comprobante->id,
                $datos['fecha'],
            );

            $this->auditoria->registrar(
                AccionAuditoria::Crear,
                AuditoriaConstants::MODULO_CONTABILIDAD,
                "Anticipo {$anticipo->numero_documento} cruzado por {$valor->aDecimalString()} contra {$documento->numero_documento}",
                'cruce_anticipo',
                $comprobante->id,
                nuevos: [
                    'anticipo_id' => $anticipo->id,
                    'documento_id' => $documento->id,
                    'valor' => $valor->aDecimalString(),
                    'comprobante_id' => $comprobante->id,
                ],
            );

            return ['anticipo' => $anticipo->fresh(), 'documento' => $documento->fresh()];
        });
    }
}
