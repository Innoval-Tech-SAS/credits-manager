<?php

namespace App\Proveedores\Repositories;

use App\Core\Base\BaseRepository;
use App\Proveedores\Models\Proveedor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ProveedorRepository extends BaseRepository
{
    public function __construct(Proveedor $proveedor)
    {
        parent::__construct($proveedor);
    }

    public function paginar(?string $busqueda, int $porPagina, int $pagina): LengthAwarePaginator
    {
        $consulta = $this->consulta()->with('tercero');

        if ($busqueda) {
            $consulta->whereHas('tercero', fn (Builder $subconsulta) => $subconsulta
                ->where('nombre', 'like', "%{$busqueda}%")
                ->orWhere('numero_documento', 'like', "%{$busqueda}%"));
        }

        return $consulta
            ->join('terceros', 'terceros.id', '=', 'proveedores.tercero_id')
            ->orderBy('terceros.nombre')
            ->select('proveedores.*')
            ->paginate(perPage: $porPagina, page: $pagina);
    }
}
