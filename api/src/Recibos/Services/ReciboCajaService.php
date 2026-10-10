<?php

namespace App\Recibos\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Contabilidad\Models\Comprobante;
use App\Core\Consecutivos\Enums\TipoDocumentoConsecutivo;
use App\Core\Consecutivos\Services\ConsecutivoDocumentoService;
use App\Empresas\Services\EmpresaService;
use App\Facturacion\Models\CobroFacturacion;
use App\Impresion\Data\PdfGenerado;
use App\Impresion\Services\GeneradorPdf;
use App\Pagos\Models\Pago;
use App\Recibos\Documentos\ReciboCajaPdf;
use App\Recibos\Enums\EstadoRecibo;
use App\Recibos\Enums\OrigenReciboCaja;
use App\Recibos\Models\ReciboCaja;
use App\Recibos\Repositories\ReciboCajaRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class ReciboCajaService
{
    public function __construct(
        protected ReciboCajaRepository $recibos,
        protected ConsecutivoDocumentoService $consecutivos,
        protected EmpresaService $empresas,
        protected GeneradorPdf $generador,
        protected EmisorAuditoria $auditoria,
    ) {}

    /** Se llama dentro de la transacción del pago: si el pago falla, el número del recibo se libera con él. */
    public function emitir(Pago $pago, ?Comprobante $comprobante): ReciboCaja
    {
        $recibo = $this->crear([
            'pago_id' => $pago->id,
            'origen_tipo' => OrigenReciboCaja::Pago,
            'comprobante_id' => $comprobante?->id,
            'emitido_por' => $pago->registrado_por,
        ]);

        $this->auditoria->registrarCreacion(AuditoriaConstants::MODULO_PAGOS, "Recibo de caja {$recibo->numero} emitido para el pago #{$pago->id}", $recibo);

        return $recibo;
    }

    /** Mismo documento que `emitir()`, pero para un cobro de factura. Se llama dentro de la transacción del cobro. */
    public function emitirParaCobro(CobroFacturacion $cobro, ?Comprobante $comprobante): ReciboCaja
    {
        $recibo = $this->crear([
            'cobro_facturacion_id' => $cobro->id,
            'origen_tipo' => OrigenReciboCaja::CobroFacturacion,
            'comprobante_id' => $comprobante?->id,
            'emitido_por' => $cobro->registrado_por,
        ]);

        $this->auditoria->registrarCreacion(AuditoriaConstants::MODULO_FACTURACION, "Recibo de caja {$recibo->numero} emitido para el cobro #{$cobro->id}", $recibo);

        return $recibo;
    }

    /** @param  array{pago_id?: int, cobro_facturacion_id?: int, origen_tipo: OrigenReciboCaja, comprobante_id: ?int, emitido_por: ?int}  $datos */
    private function crear(array $datos): ReciboCaja
    {
        $consecutivo = $this->consecutivos->siguiente(TipoDocumentoConsecutivo::ReciboCaja);
        $numero = trim(($consecutivo['prefijo'] ? $consecutivo['prefijo'].' ' : '').$consecutivo['numero']);

        return $this->recibos->crear([
            ...$datos,
            'consecutivo' => $consecutivo['numero'],
            'numero' => $numero,
            'estado' => EstadoRecibo::Emitido,
        ]);
    }

    /**
     * Anula el recibo del pago reversado, si lo tiene. Se llama dentro de la transacción de la reversa. El número se
     * conserva: la numeración no tiene huecos porque ningún recibo se elimina ni se renumera.
     */
    public function anularPorReversa(Pago $pago, string $motivo, ?int $usuarioId): void
    {
        $this->anular($this->recibos->porPago($pago->id), $motivo, $usuarioId, AuditoriaConstants::MODULO_PAGOS, "la reversa del pago #{$pago->id}");
    }

    /** Mismo reverso que `anularPorReversa()`, pero para el recibo de un cobro de factura. */
    public function anularPorReversaCobro(CobroFacturacion $cobro, string $motivo, ?int $usuarioId): void
    {
        $this->anular($this->recibos->porCobroFacturacion($cobro->id), $motivo, $usuarioId, AuditoriaConstants::MODULO_FACTURACION, "la reversa del cobro #{$cobro->id}");
    }

    private function anular(?ReciboCaja $recibo, string $motivo, ?int $usuarioId, string $modulo, string $origen): void
    {
        if ($recibo === null) {
            return;
        }

        $anteriores = $this->auditoria->instantanea($recibo);

        $this->recibos->actualizar($recibo, [
            'estado' => EstadoRecibo::Anulado,
            'anulado_en' => now(),
            'anulado_por' => $usuarioId,
            'motivo_anulacion' => $motivo,
        ]);

        $this->auditoria->registrarModificacion(
            $modulo,
            "Recibo de caja {$recibo->numero} anulado por {$origen}",
            $recibo,
            $anteriores,
            $motivo,
            AccionAuditoria::Reversar,
        );
    }

    /** @param  array{tercero_id: ?int, desde: ?string, hasta: ?string, estado: ?EstadoRecibo}  $filtros */
    public function listar(array $filtros, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->recibos->paginarFiltrado($filtros, max(1, min(100, $porPagina)), max(1, $pagina));
    }

    public function generarPdf(Pago $pago): PdfGenerado
    {
        $pago->load(['recibo.comprobante.tipoComprobante', 'recibo.comprobante.lineas.cuenta', 'recibo.anulador', 'cliente.tercero', 'detalles.cuota', 'registrador']);

        abort_if($pago->recibo === null, 404);

        $pago->recibo->setRelation('pago', $pago);

        return $this->generarDesdeRecibo($pago->recibo, AuditoriaConstants::MODULO_PAGOS);
    }

    public function generarPdfParaCobro(CobroFacturacion $cobro): PdfGenerado
    {
        $cobro->load(['recibo.comprobante.tipoComprobante', 'recibo.comprobante.lineas.cuenta', 'recibo.anulador', 'documento.cliente.tercero', 'registrador']);

        abort_if($cobro->recibo === null, 404);

        $cobro->recibo->setRelation('cobroFacturacion', $cobro);

        return $this->generarDesdeRecibo($cobro->recibo, AuditoriaConstants::MODULO_FACTURACION);
    }

    private function generarDesdeRecibo(ReciboCaja $recibo, string $modulo): PdfGenerado
    {
        $documento = new ReciboCajaPdf($recibo);
        $pdf = new PdfGenerado($this->generador->generar($documento, $this->empresas->obtenerPropia()), $documento->nombreArchivo());

        $this->auditoria->registrar(
            AccionAuditoria::Exportar,
            $modulo,
            "Recibo de caja {$recibo->numero} descargado en PDF",
            'recibo_caja',
            $recibo->id,
        );

        return $pdf;
    }
}
