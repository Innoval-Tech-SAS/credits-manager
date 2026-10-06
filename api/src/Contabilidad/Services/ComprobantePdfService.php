<?php

namespace App\Contabilidad\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Contabilidad\Documentos\ComprobantePdf;
use App\Contabilidad\Models\Comprobante;
use App\Contabilidad\Repositories\ComprobanteRepository;
use App\Empresas\Services\EmpresaService;
use App\Impresion\Data\PdfGenerado;
use App\Impresion\Services\GeneradorPdf;

class ComprobantePdfService
{
    public function __construct(
        protected ComprobanteRepository $comprobantes,
        protected EmpresaService $empresas,
        protected GeneradorPdf $generador,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function generarPdf(Comprobante $comprobante): PdfGenerado
    {
        $comprobante->load(['tipoComprobante', 'tercero', 'lineas.cuenta', 'creadoPor', 'revisor', 'comprobanteReverso.tipoComprobante']);

        // Un reverso apunta al comprobante que reversa (`origen_id`); la búsqueda pasa por el scope de empresa.
        $original = $comprobante->modulo_origen === 'reverso' ? $this->comprobantes->buscarPorId($comprobante->origen_id) : null;
        $original?->load('tipoComprobante');

        $documento = new ComprobantePdf($comprobante, $original);
        $pdf = new PdfGenerado($this->generador->generar($documento, $this->empresas->obtenerPropia()), $documento->nombreArchivo());

        $this->auditoria->registrar(
            AccionAuditoria::Exportar,
            AuditoriaConstants::MODULO_CONTABILIDAD,
            "Comprobante {$comprobante->tipoComprobante->codigo} {$comprobante->consecutivo} descargado en PDF",
            'comprobante',
            $comprobante->id,
        );

        return $pdf;
    }
}
