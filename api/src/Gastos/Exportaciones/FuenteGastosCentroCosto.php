<?php

namespace App\Gastos\Exportaciones;

use App\Exportacion\Contracts\FuenteExportable;
use App\Exportacion\Data\ColumnaExportable;
use App\Exportacion\Enums\TipoColumna;
use App\Gastos\Services\ReporteGastosCentroCostoService;
use App\Usuarios\Models\Usuario;

/** Gastos mensuales por centro de costo a Excel/CSV. Serializable: empresa y meses; el cálculo corre en el worker. */
final class FuenteGastosCentroCosto implements FuenteExportable
{
    public function __construct(
        private readonly int $empresaId,
        private readonly string $desdeMes,
        private readonly string $hastaMes,
    ) {}

    public function reporte(): string
    {
        return 'gastos.centro_costo';
    }

    public function modulo(): string
    {
        return 'gastos';
    }

    public function titulo(): string
    {
        return 'Gastos por centro de costo';
    }

    public function nombreArchivo(): string
    {
        return "gastos-centro-costo-{$this->desdeMes}-{$this->hastaMes}";
    }

    public function columnas(): array
    {
        return [
            new ColumnaExportable('mes', 'Mes'),
            new ColumnaExportable('centro_codigo', 'Código centro'),
            new ColumnaExportable('centro_nombre', 'Centro de costo'),
            new ColumnaExportable('cantidad', 'Gastos', TipoColumna::Entero),
            new ColumnaExportable('valor_bruto', 'Valor bruto', TipoColumna::Monetario),
            new ColumnaExportable('valor_neto', 'Valor neto', TipoColumna::Monetario),
        ];
    }

    public function filas(): iterable
    {
        $resumen = app(ReporteGastosCentroCostoService::class)->resumen($this->empresaId, $this->desdeMes, $this->hastaMes);

        foreach ($resumen['filas'] as $fila) {
            yield [
                'mes' => $fila['mes'],
                'centro_codigo' => $fila['centro_costo']['codigo'] ?? null,
                'centro_nombre' => $fila['centro_costo']['nombre'] ?? 'Sin centro de costo',
                'cantidad' => $fila['cantidad'],
                'valor_bruto' => $fila['valor_bruto'],
                'valor_neto' => $fila['valor_neto'],
            ];
        }

        yield [
            'mes' => null,
            'centro_codigo' => null,
            'centro_nombre' => 'Total',
            'cantidad' => $resumen['total_cantidad'],
            'valor_bruto' => $resumen['total_bruto'],
            'valor_neto' => $resumen['total_neto'],
        ];
    }

    public function totalFilas(): int
    {
        return count(app(ReporteGastosCentroCostoService::class)->resumen($this->empresaId, $this->desdeMes, $this->hastaMes)['filas']) + 1;
    }

    public function filtrosAplicados(): array
    {
        return ['desde' => $this->desdeMes, 'hasta' => $this->hastaMes];
    }

    public function autorizadoPara(Usuario $usuario): bool
    {
        return $usuario->empresa_id !== null && $usuario->tienePermiso('gastos', 'exportar');
    }
}
