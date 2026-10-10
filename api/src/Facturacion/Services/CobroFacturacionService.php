<?php

namespace App\Facturacion\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Contabilidad\Enums\NaturalezaDocumento;
use App\Contabilidad\Services\ComprobanteService;
use App\Contabilidad\Services\DocumentoTerceroService;
use App\Core\Dinero\Dinero;
use App\Facturacion\Enums\EstadoDocumentoFacturacion;
use App\Facturacion\Enums\MedioCobroFacturacion;
use App\Facturacion\Enums\MovimientoCobro;
use App\Facturacion\Enums\SituacionFactura;
use App\Facturacion\Enums\TipoDocumentoFacturacion;
use App\Facturacion\Models\CobroFacturacion;
use App\Facturacion\Models\DocumentoFacturacion;
use App\Facturacion\Repositories\CobroFacturacionRepository;
use App\Facturacion\Repositories\DocumentoFacturacionRepository;
use App\Recibos\Services\ReciboCajaService;
use App\Usuarios\Models\Usuario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cobros sobre facturas. Un cobro no supera el saldo de la factura en el momento de registrarlo, y su asiento
 * se crea en la misma transacción. Un cobro no se edita ni se borra: se corrige con una reversa, que anula su
 * asiento (reverso enlazado al original) y deja una fila compensatoria. `registrarMultiple()` deja aplicar un
 * recibo a varias facturas del mismo cliente a la vez, cada una con su propio cobro y asiento.
 */
class CobroFacturacionService
{
    public function __construct(
        protected DocumentoFacturacionRepository $documentos,
        protected CobroFacturacionRepository $cobros,
        protected DocumentoFacturacionService $facturas,
        protected AsientosFacturacionService $asientos,
        protected ComprobanteService $comprobantes,
        protected DocumentoTerceroService $documentosTercero,
        protected ReciboCajaService $recibos,
        protected EmisorAuditoria $auditoria,
    ) {}

    /** @param array{fecha: string, valor: string, medio: MedioCobroFacturacion, referencia?: ?string, observacion?: ?string} $datos */
    public function registrar(DocumentoFacturacion $factura, array $datos, Usuario $usuario): CobroFacturacion
    {
        return DB::transaction(function () use ($factura, $datos, $usuario) {
            $bloqueada = $this->documentos->bloquear($factura->id);

            return $this->registrarSobreFacturaBloqueada($bloqueada, $datos, $usuario);
        });
    }

    /**
     * Un cobro que cruza varias facturas del mismo cliente en un solo recibo: misma validación y mismo asiento por
     * factura que `registrar()` (reutilizado aquí), solo que las N facturas se bloquean y se cobran en una única
     * transacción — si una falla (saldo insuficiente, ya anulada...), ninguna queda cobrada. A propósito NO comparten
     * un solo comprobante: cada `cobros_facturacion` sigue siendo responsable de exactamente un asiento, para que
     * `reversar()` pueda deshacer una factura del recibo sin arrastrar a las demás.
     *
     * @param  array{tercero_id: int, fecha: string, medio: MedioCobroFacturacion, referencia?: ?string, observacion?: ?string, aplicaciones: list<array{documento_facturacion_id: int, valor: string}>}  $datos
     * @return Collection<int, CobroFacturacion>
     */
    public function registrarMultiple(array $datos, Usuario $usuario): Collection
    {
        return DB::transaction(function () use ($datos, $usuario) {
            $ids = array_column($datos['aplicaciones'], 'documento_facturacion_id');
            $facturas = $this->documentos->bloquearVarias($ids)->keyBy('id');

            if ($facturas->count() !== count(array_unique($ids))) {
                throw ValidationException::withMessages(['aplicaciones' => __('validation.custom.documento.no_pendiente_o_no_pertenece')]);
            }

            $terceroId = (int) $datos['tercero_id'];

            foreach ($facturas as $factura) {
                if ($factura->cliente->tercero_id !== $terceroId) {
                    throw ValidationException::withMessages(['aplicaciones' => __('validation.custom.documento.no_pendiente_o_no_pertenece')]);
                }
            }

            return collect($datos['aplicaciones'])->map(function (array $aplicacion) use ($facturas, $datos, $usuario) {
                $factura = $facturas[$aplicacion['documento_facturacion_id']];

                return $this->registrarSobreFacturaBloqueada($factura, [
                    'fecha' => $datos['fecha'],
                    'valor' => $aplicacion['valor'],
                    'medio' => $datos['medio'],
                    'referencia' => $datos['referencia'] ?? null,
                    'observacion' => $datos['observacion'] ?? null,
                ], $usuario);
            })->values();
        });
    }

    /** @param array{fecha: string, valor: string, medio: MedioCobroFacturacion, referencia?: ?string, observacion?: ?string} $datos */
    private function registrarSobreFacturaBloqueada(DocumentoFacturacion $bloqueada, array $datos, Usuario $usuario): CobroFacturacion
    {
        if ($bloqueada->tipo !== TipoDocumentoFacturacion::Factura || $bloqueada->estado !== EstadoDocumentoFacturacion::Emitido) {
            throw ValidationException::withMessages(['documento' => __('validation.custom.facturacion.cobro_no_factura_emitida')]);
        }

        $totales = $this->facturas->totales($bloqueada);

        if ($totales['situacion'] === SituacionFactura::Anulada) {
            throw ValidationException::withMessages(['documento' => __('validation.custom.facturacion.ya_anulada')]);
        }

        $valor = Dinero::desdePesos($datos['valor']);

        if ($valor->mayorQue($totales['saldo'])) {
            throw ValidationException::withMessages([
                'valor' => __('validation.custom.facturacion.cobro_excede_saldo', ['saldo' => $totales['saldo']->aDecimalString()]),
            ]);
        }

        $asiento = $this->asientos->contabilizarCobro($bloqueada, $valor, $datos['fecha']);

        $cobro = $this->cobros->crear([
            'documento_id' => $bloqueada->id,
            'tipo' => MovimientoCobro::Cobro,
            'comprobante_id' => $asiento->id,
            'fecha' => $datos['fecha'],
            'valor' => $valor,
            'medio' => $datos['medio'],
            'referencia' => $datos['referencia'] ?? null,
            'observacion' => $datos['observacion'] ?? null,
            'registrado_por' => $usuario->id,
        ]);

        $this->recibos->emitirParaCobro($cobro, $asiento);

        if ($bloqueada->documento_tercero_id) {
            $this->documentosTercero->aplicarValor(
                $bloqueada->cliente->tercero,
                NaturalezaDocumento::PorCobrar,
                [['documento_tercero_id' => $bloqueada->documento_tercero_id, 'valor' => $valor]],
                'facturacion_cobro',
                $cobro->id,
                $datos['fecha'],
            );
        }

        $this->auditoria->registrar(
            AccionAuditoria::Crear,
            AuditoriaConstants::MODULO_FACTURACION,
            "Cobro de {$valor->aDecimalString()} registrado en la factura {$bloqueada->numero}",
            'cobro_facturacion',
            $cobro->id,
            nuevos: [
                'documento_id' => $bloqueada->id,
                'valor' => $valor->aDecimalString(),
                'medio' => $cobro->medio->value,
                'referencia' => $cobro->referencia,
                'fecha' => $cobro->fecha->toDateString(),
            ],
            autor: $usuario,
        );

        return $cobro;
    }

    /** Reversa el cobro completo: su asiento queda anulado con un reverso enlazado y el cobro original no cambia. */
    public function reversar(CobroFacturacion $cobro, string $motivo, Usuario $usuario): CobroFacturacion
    {
        return DB::transaction(function () use ($cobro, $motivo, $usuario) {
            // La factura se bloquea antes de leer la reversa: dos reversas simultáneas del mismo cobro se serializan.
            $factura = $this->documentos->bloquear($cobro->documento_id);

            if ($cobro->tipo !== MovimientoCobro::Cobro) {
                throw ValidationException::withMessages(['cobro' => __('validation.custom.facturacion.cobro_no_reversible')]);
            }

            if ($this->cobros->reversaDe($cobro->id) !== null) {
                throw ValidationException::withMessages(['cobro' => __('validation.custom.facturacion.cobro_ya_reversado')]);
            }

            $reverso = $this->comprobantes->reversarComprobante($cobro->comprobante, $motivo);
            $this->recibos->anularPorReversaCobro($cobro, $motivo, $usuario->id);

            $reversa = $this->cobros->crear([
                'documento_id' => $factura->id,
                'tipo' => MovimientoCobro::Reversa,
                'cobro_origen_id' => $cobro->id,
                'comprobante_id' => $reverso->id,
                'fecha' => now('America/Bogota')->toDateString(),
                'valor' => $cobro->valor,
                'medio' => $cobro->medio,
                'referencia' => $cobro->referencia,
                'motivo' => $motivo,
                'registrado_por' => $usuario->id,
            ]);

            if ($factura->documento_tercero_id) {
                $this->documentosTercero->incrementarValor(
                    $factura->documentoTercero,
                    $cobro->valor,
                    'facturacion_cobro_reversa',
                    $reversa->id,
                );
            }

            $this->auditoria->registrar(
                AccionAuditoria::Reversar,
                AuditoriaConstants::MODULO_FACTURACION,
                "Cobro #{$cobro->id} de {$cobro->valor->aDecimalString()} reversado en la factura {$factura->numero}",
                'cobro_facturacion',
                $cobro->id,
                nuevos: ['reversa_id' => $reversa->id, 'comprobante_reverso_id' => $reverso->id],
                motivo: $motivo,
                autor: $usuario,
            );

            return $reversa;
        });
    }
}
