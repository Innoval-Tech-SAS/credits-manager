<?php

namespace App\Contabilidad\Repositories;

use App\Contabilidad\Models\MigracionApertura;
use App\Core\Base\BaseRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class MigracionAperturaRepository extends BaseRepository
{
    public function __construct(MigracionApertura $migracionApertura)
    {
        parent::__construct($migracionApertura);
    }

    public function paginar(?string $busqueda, int $porPagina, int $pagina): LengthAwarePaginator
    {
        $consulta = $this->consulta()->orderByDesc('creado_en');

        if ($busqueda) {
            $consulta->where('archivo_original', 'like', "%{$busqueda}%");
        }

        return $consulta->paginate(perPage: $porPagina, page: $pagina);
    }
}
