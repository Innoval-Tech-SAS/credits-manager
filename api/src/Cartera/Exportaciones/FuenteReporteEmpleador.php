<?php

namespace App\Cartera\Exportaciones;

use App\Cartera\Data\ReporteEmpleador;
use App\Exportacion\Contracts\FuenteExportable;
use App\Exportacion\Data\ColumnaExportable;
use App\Exportacion\Enums\TipoColumna;
use App\Exportacion\Services\AccesoExportaciones;
use App\Usuarios\Models\Usuario;
use Illuminate\Support\Str;

/**
 * Relación de descuento por empleador a Excel/CSV. Lleva el reporte ya calculado con la sesión del usuario
 * (foto del momento de la solicitud): el worker corre sin sesión y no debe volver a consultar cartera,
 * porque sin usuario autenticado el scope de empresa no filtra.
 */
final class FuenteReporteEmpleador implements FuenteExportable
{
    public function __construct(private readonly ReporteEmpleador $reporte) {}

    public function reporte(): string
    {
        return 'cartera.reporte_empleador';
    }

    public function modulo(): string
    {
        return 'cartera';
    }

    public function titulo(): string
    {
        return 'Relación de descuento por empleador';
    }

    public function nombreArchivo(): string
    {
        return 'relacion-descuento-'.Str::slug($this->reporte->empleadorNombre);
    }

    public function columnas(): array
    {
        $columnas = [
            new ColumnaExportable('empleado', 'Empleado'),
            new ColumnaExportable('cuota', 'Cuota', TipoColumna::Monetario),
        ];

        foreach ($this->reporte->meses as $periodo) {
            $etiqueta = $this->reporte->etiquetaMes($periodo);

            $columnas[] = new ColumnaExportable("valor_{$periodo}", $etiqueta, TipoColumna::Monetario);
            $columnas[] = new ColumnaExportable("estado_{$periodo}", "Estado {$etiqueta}");
        }

        return $columnas;
    }

    public function filas(): iterable
    {
        foreach ($this->reporte->filas as $fila) {
            $celdas = ['empleado' => $fila['cliente_nombre'], 'cuota' => $fila['valor_cuota']];

            foreach ($this->reporte->meses as $periodo) {
                $cuota = $fila['cuotas'][$periodo] ?? null;

                $celdas["valor_{$periodo}"] = $cuota['valor'] ?? null;
                $celdas["estado_{$periodo}"] = $cuota ? ucfirst($cuota['estado']) : null;
            }

            yield $celdas;
        }

        $totales = ['empleado' => 'Total por mes', 'cuota' => null];

        foreach ($this->reporte->meses as $periodo) {
            $totales["valor_{$periodo}"] = $this->reporte->totalesPorMes[$periodo];
            $totales["estado_{$periodo}"] = null;
        }

        yield $totales;
    }

    public function totalFilas(): int
    {
        return count($this->reporte->filas) + 1;
    }

    public function filtrosAplicados(): array
    {
        return ['empleador' => $this->reporte->empleadorNombre];
    }

    public function autorizadoPara(Usuario $usuario): bool
    {
        return app(AccesoExportaciones::class)->puede($usuario, $this->reporte());
    }
}
