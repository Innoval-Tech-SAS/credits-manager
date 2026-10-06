<?php

namespace App\Gastos\Repositories;

use App\Core\Base\BaseRepository;
use App\Gastos\Enums\EstadoGasto;
use App\Gastos\Models\Gasto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class GastoRepository extends BaseRepository
{
    public function __construct(Gasto $gasto)
    {
        parent::__construct($gasto);
    }

    public function paginar(?string $busqueda, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->filtrar($this->consulta()->with(['tipoGasto', 'tercero']), $busqueda)
            ->orderByDesc('fecha')
            ->paginate($porPagina, page: $pagina);
    }

    /** Mismo listado que la pantalla, con la empresa explícita para exportar desde el worker, sin sesión. */
    public function consultaParaExportar(int $empresaId, ?string $busqueda): Builder
    {
        $consulta = $this->consulta()
            ->where('gastos.empresa_id', $empresaId)
            ->with(['tipoGasto', 'tercero'])
            ->orderByDesc('fecha');

        return $this->filtrar($consulta, $busqueda);
    }

    /**
     * Gastos causados por mes y centro de costo, en centavos. El centro nulo son los gastos anteriores a la obligatoriedad.
     * La empresa va explícita: el reporte también corre en el worker de exportación, sin sesión.
     */
    public function resumenPorCentroCosto(int $empresaId, string $desde, string $hasta): Collection
    {
        return DB::table('gastos')
            ->leftJoin('centros_costo', 'centros_costo.id', '=', 'gastos.centro_costo_id')
            ->where('gastos.empresa_id', $empresaId)
            ->where('gastos.estado', EstadoGasto::Causado->value)
            ->whereBetween('gastos.fecha', [$desde, $hasta])
            ->whereNull('gastos.borrado_en')
            ->groupBy('mes', 'gastos.centro_costo_id', 'centros_costo.codigo', 'centros_costo.nombre')
            ->orderBy('mes')
            ->orderBy('centros_costo.codigo')
            ->selectRaw('SUBSTR(gastos.fecha, 1, 7) as mes, gastos.centro_costo_id, centros_costo.codigo as centro_codigo, centros_costo.nombre as centro_nombre, COUNT(*) as cantidad, COALESCE(SUM(gastos.valor_bruto),0) as valor_bruto, COALESCE(SUM(gastos.valor_neto),0) as valor_neto')
            ->get();
    }

    public function cursorParaExportar(int $empresaId, ?string $busqueda): LazyCollection
    {
        return $this->consultaParaExportar($empresaId, $busqueda)->lazy();
    }

    public function contarParaExportar(int $empresaId, ?string $busqueda): int
    {
        return $this->consultaParaExportar($empresaId, $busqueda)->reorder()->count();
    }

    private function filtrar(Builder $consulta, ?string $busqueda): Builder
    {
        return $consulta->when($busqueda, fn ($query) => $query->where(function ($query) use ($busqueda) {
            $query->where('numero_documento', 'like', "%{$busqueda}%")
                ->orWhere('concepto', 'like', "%{$busqueda}%");
        }));
    }

    public function obtenerConDetalle(Gasto $gasto): Gasto
    {
        return $gasto->load(['tipoGasto', 'tercero', 'municipio', 'parametrizacion', 'comprobante.lineas', 'documentoTercero.aplicaciones', 'reglasFiscalesAplicadas']);
    }
}
