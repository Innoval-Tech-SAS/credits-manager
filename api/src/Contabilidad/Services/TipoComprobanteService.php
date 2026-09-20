<?php

namespace App\Contabilidad\Services;

use App\Contabilidad\Models\TipoComprobante;
use App\Contabilidad\Repositories\TipoComprobanteRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class TipoComprobanteService
{
    public function __construct(protected TipoComprobanteRepository $tipos) {}

    public function paginar(?string $busqueda, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->tipos->paginar($busqueda, max(1, min(100, $porPagina)), max(1, $pagina));
    }

    public function crear(array $datos): TipoComprobante
    {
        return $this->tipos->crear($datos);
    }
}
