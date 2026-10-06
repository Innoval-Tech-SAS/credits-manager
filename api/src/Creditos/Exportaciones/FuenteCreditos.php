<?php

namespace App\Creditos\Exportaciones;

use App\Creditos\Enums\EstadoCredito;
use App\Creditos\Repositories\CreditoRepository;
use App\Exportacion\Contracts\FuenteExportable;
use App\Exportacion\Data\ColumnaExportable;
use App\Exportacion\Enums\TipoColumna;
use App\Usuarios\Models\Usuario;

/**
 * Listado de créditos a Excel/CSV, con los mismos filtros de la pantalla. Serializable: empresa, asesor con
 * alcance (null = toda la empresa), búsqueda y estado; las filas se leen con cursor en el worker.
 */
final class FuenteCreditos implements FuenteExportable
{
    public function __construct(
        private readonly int $empresaId,
        private readonly ?int $asesorId,
        private readonly ?string $busqueda,
        private readonly ?string $estado,
    ) {}

    public function reporte(): string
    {
        return 'creditos.listado';
    }

    public function modulo(): string
    {
        return 'creditos';
    }

    public function titulo(): string
    {
        return 'Créditos';
    }

    public function nombreArchivo(): string
    {
        return 'creditos-'.now('America/Bogota')->format('Y-m-d');
    }

    public function columnas(): array
    {
        return [
            new ColumnaExportable('credito', 'Crédito (id)', TipoColumna::Entero),
            new ColumnaExportable('cliente', 'Cliente'),
            new ColumnaExportable('tipo_credito', 'Tipo'),
            new ColumnaExportable('estado', 'Estado'),
            new ColumnaExportable('fecha_desembolso', 'Fecha de desembolso', TipoColumna::Fecha),
            new ColumnaExportable('valor_prestamo', 'Valor del préstamo', TipoColumna::Monetario),
            new ColumnaExportable('tasa_interes', 'Tasa de interés', TipoColumna::Decimal),
            new ColumnaExportable('plazo', 'Plazo', TipoColumna::Entero),
            new ColumnaExportable('valor_cuota', 'Valor de la cuota', TipoColumna::Monetario),
        ];
    }

    public function filas(): iterable
    {
        foreach (app(CreditoRepository::class)->cursorParaExportar($this->empresaId, $this->asesorId, $this->busqueda, $this->estadoEnum()) as $credito) {
            yield [
                'credito' => $credito->id,
                'cliente' => $credito->cliente->tercero->nombre,
                'tipo_credito' => $credito->tipo_credito->value,
                'estado' => $credito->estado->value,
                'fecha_desembolso' => $credito->fecha_desembolso,
                'valor_prestamo' => $credito->valor_prestamo,
                'tasa_interes' => $credito->tasa_interes,
                'plazo' => $credito->plazo,
                'valor_cuota' => $credito->valor_cuota_total,
            ];
        }
    }

    public function totalFilas(): int
    {
        return app(CreditoRepository::class)->contarParaExportar($this->empresaId, $this->asesorId, $this->busqueda, $this->estadoEnum());
    }

    public function filtrosAplicados(): array
    {
        return ['busqueda' => $this->busqueda, 'estado' => $this->estado, 'asesor_id' => $this->asesorId];
    }

    public function autorizadoPara(Usuario $usuario): bool
    {
        return $usuario->empresa_id !== null && $usuario->tienePermiso('creditos', 'exportar');
    }

    private function estadoEnum(): ?EstadoCredito
    {
        return $this->estado === null ? null : EstadoCredito::from($this->estado);
    }
}
