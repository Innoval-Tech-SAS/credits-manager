<?php

namespace App\Recibos\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Contabilidad\Models\Comprobante;
use App\Core\Consecutivos\Enums\TipoDocumentoConsecutivo;
use App\Core\Consecutivos\Services\ConsecutivoDocumentoService;
use App\Empresas\Services\EmpresaService;
use App\Impresion\Data\PdfGenerado;
use App\Impresion\Services\GeneradorPdf;
use App\Pagos\Models\Pago;
use App\Recibos\Documentos\ReciboCajaPdf;
use App\Recibos\Enums\EstadoRecibo;
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
        $consecutivo = $this->consecutivos->siguiente(TipoDocumentoConsecutivo::ReciboCaja);
        $numero = trim(($consecutivo['prefijo'] ? $consecutivo['prefijo'].' ' : '').$consecutivo['numero']);

        $recibo = $this->recibos->crear([
            'pago_id' => $pago->id,
            'comprobante_id' => $comprobante?->id,
            'consecutivo' => $consecutivo['numero'],
            'numero' => $numero,
            'estado' => EstadoRecibo::Emitido,
            'emitido_por' => $pago->registrado_por,
        ]);

        $this->auditoria->registrarCreacion(AuditoriaConstants::MODULO_PAGOS, "Recibo de caja {$numero} emitido para el pago #{$pago->id}", $recibo);

        return $recibo;
    }

    /**
     * Anula el recibo del pago reversado, si lo tiene. Se llama dentro de la transacción de la reversa. El número se
     * conserva: la numeración no tiene huecos porque ningún recibo se elimina ni se renumera.
     */
    public function anularPorReversa(Pago $pago, string $motivo, ?int $usuarioId): void
    {
        $recibo = $this->recibos->porPago($pago->id);

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
            AuditoriaConstants::MODULO_PAGOS,
            "Recibo de caja {$recibo->numero} anulado por la reversa del pago #{$pago->id}",
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

        $documento = new ReciboCajaPdf($pago);
        $pdf = new PdfGenerado($this->generador->generar($documento, $this->empresas->obtenerPropia()), $documento->nombreArchivo());

        $this->auditoria->registrar(
            AccionAuditoria::Exportar,
            AuditoriaConstants::MODULO_PAGOS,
            "Recibo de caja {$pago->recibo->numero} descargado en PDF",
            'recibo_caja',
            $pago->recibo->id,
        );

        return $pdf;
    }
}
