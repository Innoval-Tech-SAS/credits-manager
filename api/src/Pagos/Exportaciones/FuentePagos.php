<?php

namespace App\Pagos\Exportaciones;

use App\Core\Dinero\Dinero;
use App\Exportacion\Contracts\FuenteExportable;
use App\Exportacion\Data\ColumnaExportable;
use App\Exportacion\Enums\TipoColumna;
use App\Pagos\Repositories\PagoRepository;
use App\Usuarios\Models\Usuario;

/**
 * Historial de pagos a Excel/CSV, con los mismos filtros de la pantalla. Serializable: empresa, asesor con
 * alcance (null = toda la empresa) y búsqueda; las filas se leen con cursor en el worker.
 */
final class FuentePagos implements FuenteExportable
{
    public function __construct(
        private readonly int $empresaId,
        private readonly ?int $asesorId,
        private readonly ?string $busqueda,
        private readonly ?string $mes,
    ) {}

    public function reporte(): string
    {
        return 'pagos.listado';
    }

    public function modulo(): string
    {
        return 'pagos';
    }

    public function titulo(): string
    {
        return 'Pagos';
    }

    public function nombreArchivo(): string
    {
        return 'pagos-'.now('America/Bogota')->format('Y-m-d');
    }

    public function columnas(): array
    {
        return [
            new ColumnaExportable('pago', 'Pago (id)', TipoColumna::Entero),
            new ColumnaExportable('fecha', 'Fecha', TipoColumna::Fecha),
            new ColumnaExportable('cliente', 'Cliente'),
            new ColumnaExportable('credito', 'Crédito (id)', TipoColumna::Entero),
            new ColumnaExportable('forma_pago', 'Forma de pago'),
            new ColumnaExportable('valor', 'Valor', TipoColumna::Monetario),
            new ColumnaExportable('capital', 'Capital', TipoColumna::Monetario),
            new ColumnaExportable('intereses', 'Intereses', TipoColumna::Monetario),
            new ColumnaExportable('otros_gastos', 'Otros gastos', TipoColumna::Monetario),
            new ColumnaExportable('recibo', 'Recibo (número)', TipoColumna::Entero),
            new ColumnaExportable('observacion', 'Observación'),
            new ColumnaExportable('estado', 'Estado'),
        ];
    }

    public function filas(): iterable
    {
        $total = Dinero::cero();
        $totales = ['capital' => Dinero::cero(), 'intereses' => Dinero::cero(), 'otros_gastos' => Dinero::cero()];

        foreach (app(PagoRepository::class)->cursorParaExportar($this->empresaId, $this->asesorId, $this->busqueda, $this->mes) as $pago) {
            $reversado = $pago->reversa !== null;

            // Un pago reversado sigue en el listado para trazabilidad, pero no suma al total recaudado.
            if (! $reversado) {
                $total = $total->sumar($pago->valor_pago);
                $totales['capital'] = $totales['capital']->sumar($pago->valor_capital ?? Dinero::cero());
                $totales['intereses'] = $totales['intereses']->sumar($pago->valor_interes ?? Dinero::cero());
                $totales['otros_gastos'] = $totales['otros_gastos']->sumar($pago->valor_otros_gastos ?? Dinero::cero());
            }

            yield [
                'pago' => $pago->id,
                'fecha' => $pago->fecha_pago,
                'cliente' => $pago->cliente->tercero->nombre,
                'credito' => $pago->credito_id,
                'forma_pago' => $pago->forma_pago->value,
                'estado' => $reversado ? 'Reversado' : 'Vigente',
                'valor' => $pago->valor_pago,
                'capital' => $pago->valor_capital ?? Dinero::cero(),
                'intereses' => $pago->valor_interes ?? Dinero::cero(),
                'otros_gastos' => $pago->valor_otros_gastos ?? Dinero::cero(),
                'recibo' => $pago->recibo?->numero,
                'observacion' => $pago->observacion,
            ];
        }

        yield [
            'pago' => null,
            'fecha' => null,
            'cliente' => 'Total',
            'credito' => null,
            'forma_pago' => null,
            'estado' => null,
            'valor' => $total,
            'capital' => $totales['capital'],
            'intereses' => $totales['intereses'],
            'otros_gastos' => $totales['otros_gastos'],
            'recibo' => null,
            'observacion' => null,
        ];
    }

    public function totalFilas(): int
    {
        return app(PagoRepository::class)->contarParaExportar($this->empresaId, $this->asesorId, $this->busqueda, $this->mes) + 1;
    }

    public function filtrosAplicados(): array
    {
        return ['busqueda' => $this->busqueda, 'mes' => $this->mes, 'asesor_id' => $this->asesorId];
    }

    public function autorizadoPara(Usuario $usuario): bool
    {
        return $usuario->empresa_id !== null && $usuario->tienePermiso('pagos', 'exportar');
    }
}
