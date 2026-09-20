<?php

namespace App\Gastos\Repositories;

use App\Core\Base\BaseRepository;
use App\Gastos\Models\Gasto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GastoRepository extends BaseRepository
{
    public function __construct(Gasto $gasto)
    {
        parent::__construct($gasto);
    }

    public function paginar(?string $busqueda, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->consulta()
            ->with(['tipoGasto', 'tercero'])
            ->when($busqueda, fn ($query) => $query->where(function ($query) use ($busqueda) {
                $query->where('numero_documento', 'like', "%{$busqueda}%")
                    ->orWhere('concepto', 'like', "%{$busqueda}%");
            }))
            ->orderByDesc('fecha')
            ->paginate($porPagina, page: $pagina);
    }

    public function obtenerConDetalle(Gasto $gasto): Gasto
    {
        return $gasto->load(['tipoGasto', 'tercero', 'municipio', 'parametrizacion', 'comprobante.lineas', 'documentoTercero.aplicaciones', 'reglasFiscalesAplicadas']);
    }
}
