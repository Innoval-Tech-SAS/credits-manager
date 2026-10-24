<?php

namespace App\Gastos\Exportaciones;

use App\Core\Dinero\Dinero;
use App\Exportacion\Contracts\FuenteExportable;
use App\Exportacion\Data\ColumnaExportable;
use App\Exportacion\Enums\TipoColumna;
use App\Exportacion\Services\AccesoExportaciones;
use App\Gastos\Repositories\GastoRepository;
use App\Usuarios\Models\Usuario;

/**
 * Listado de gastos a Excel/CSV, con la misma búsqueda de la pantalla. Serializable: empresa y búsqueda;
 * las filas se leen con cursor en el worker.
 */
final class FuenteGastos implements FuenteExportable
{
    public function __construct(
        private readonly int $empresaId,
        private readonly ?string $busqueda,
    ) {}

    public function reporte(): string
    {
        return 'gastos.listado';
    }

    public function modulo(): string
    {
        return 'gastos';
    }

    public function titulo(): string
    {
        return 'Gastos';
    }

    public function nombreArchivo(): string
    {
        return 'gastos-'.now('America/Bogota')->format('Y-m-d');
    }

    public function columnas(): array
    {
        return [
            new ColumnaExportable('id', 'Gasto (id)', TipoColumna::Entero),
            new ColumnaExportable('fecha', 'Fecha', TipoColumna::Fecha),
            new ColumnaExportable('numero_documento', 'Número de documento'),
            new ColumnaExportable('tipo_gasto', 'Tipo de gasto'),
            new ColumnaExportable('tercero', 'Tercero'),
            new ColumnaExportable('concepto', 'Concepto'),
            new ColumnaExportable('valor_bruto', 'Valor bruto', TipoColumna::Monetario),
            new ColumnaExportable('valor_iva', 'IVA', TipoColumna::Monetario),
            new ColumnaExportable('valor_retefuente', 'Retefuente', TipoColumna::Monetario),
            new ColumnaExportable('valor_reteica', 'ReteICA', TipoColumna::Monetario),
            new ColumnaExportable('valor_reteiva', 'ReteIVA', TipoColumna::Monetario),
            new ColumnaExportable('valor_neto', 'Valor neto', TipoColumna::Monetario),
            new ColumnaExportable('estado', 'Estado'),
        ];
    }

    public function filas(): iterable
    {
        $total = Dinero::cero();

        foreach (app(GastoRepository::class)->cursorParaExportar($this->empresaId, $this->busqueda) as $gasto) {
            $total = $total->sumar($gasto->valor_neto);

            yield [
                'id' => $gasto->id,
                'fecha' => $gasto->fecha,
                'numero_documento' => $gasto->numero_documento,
                'tipo_gasto' => $gasto->tipoGasto?->nombre,
                'tercero' => $gasto->tercero?->nombre,
                'concepto' => $gasto->concepto,
                'valor_bruto' => $gasto->valor_bruto,
                'valor_iva' => $gasto->valor_iva,
                'valor_retefuente' => $gasto->valor_retefuente,
                'valor_reteica' => $gasto->valor_reteica,
                'valor_reteiva' => $gasto->valor_reteiva,
                'valor_neto' => $gasto->valor_neto,
                'estado' => $gasto->estado->value,
            ];
        }

        yield [
            'id' => null,
            'fecha' => null,
            'numero_documento' => null,
            'tipo_gasto' => null,
            'tercero' => null,
            'concepto' => 'Total',
            'valor_bruto' => null,
            'valor_iva' => null,
            'valor_retefuente' => null,
            'valor_reteica' => null,
            'valor_reteiva' => null,
            'valor_neto' => $total,
            'estado' => null,
        ];
    }

    public function totalFilas(): int
    {
        return app(GastoRepository::class)->contarParaExportar($this->empresaId, $this->busqueda) + 1;
    }

    public function filtrosAplicados(): array
    {
        return ['busqueda' => $this->busqueda];
    }

    public function autorizadoPara(Usuario $usuario): bool
    {
        return $usuario->empresa_id !== null && app(AccesoExportaciones::class)->puede($usuario, $this->reporte())
            && $usuario->tienePermiso('gastos', 'listar_gastos');
    }
}
