<?php

namespace App\Gastos\Repositories;

use App\Core\Base\BaseRepository;
use App\Gastos\Models\Gasto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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
