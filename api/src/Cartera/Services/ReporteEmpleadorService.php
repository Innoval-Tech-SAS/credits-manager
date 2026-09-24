<?php

namespace App\Cartera\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Cartera\Data\ReporteEmpleador;
use App\Cartera\Documentos\ReporteEmpleadorPdf;
use App\Cartera\Exportaciones\FuenteReporteEmpleador;
use App\Empleadores\Models\Empleador;
use App\Empleadores\Repositories\EmpleadorRepository;
use App\Empresas\Services\EmpresaService;
use App\Exportacion\Enums\FormatoExportacion;
use App\Exportacion\Models\Exportacion;
use App\Exportacion\Services\ExportacionService;
use App\Impresion\Data\PdfGenerado;
use App\Impresion\Services\GeneradorPdf;
use App\Usuarios\Models\Usuario;

/** Salidas del reporte por empleador (PDF y Excel) sobre los mismos datos que se ven en pantalla. */
class ReporteEmpleadorService
{
    public function __construct(
        protected CarteraService $cartera,
        protected EmpleadorRepository $empleadores,
        protected EmpresaService $empresas,
        protected GeneradorPdf $generador,
        protected ExportacionService $exportaciones,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function generarPdf(int $empleadorId): PdfGenerado
    {
        $reporte = $this->armar($empleadorId);
        $documento = new ReporteEmpleadorPdf($reporte, now());

        $pdf = new PdfGenerado($this->generador->generar($documento, $this->empresas->obtenerPropia()), $documento->nombreArchivo());

        $this->auditoria->registrar(
            AccionAuditoria::Exportar,
            AuditoriaConstants::MODULO_CARTERA,
            "Relación de descuento del empleador #{$empleadorId} descargada en PDF",
            'empleador',
            $empleadorId,
        );

        return $pdf;
    }

    public function solicitarExportacion(Usuario $usuario, int $empleadorId, FormatoExportacion $formato): Exportacion
    {
        return $this->exportaciones->solicitar(new FuenteReporteEmpleador($this->armar($empleadorId)), $formato, $usuario);
    }

    private function armar(int $empleadorId): ReporteEmpleador
    {
        /** @var Empleador $empleador */
        $empleador = $this->empleadores->buscarPorIdOFallar($empleadorId);
        $datos = $this->cartera->reporteEmpleador($empleadorId);

        $filas = array_map(fn (array $fila) => [
            'cliente_id' => $fila['cliente_id'],
            'cliente_nombre' => $fila['cliente_nombre'],
            'valor_cuota' => $fila['valor_cuota'],
            'cuotas' => array_map(fn (array $cuota) => ['valor' => $cuota['valor'], 'estado' => $cuota['estado']->value], $fila['cuotas']),
        ], $datos['filas']);

        return new ReporteEmpleador(
            $empleador->id,
            $empleador->nombre,
            $empleador->nit,
            $empleador->telefono,
            $empleador->direccion,
            $datos['meses'],
            $filas,
            $datos['totales_por_mes'],
        );
    }
}
