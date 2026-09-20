<?php

namespace App\Usuarios\Services;

use App\Usuarios\Repositories\PermisoRepository;
use Illuminate\Database\Eloquent\Collection;

class PermisoService
{
    public function __construct(protected PermisoRepository $permisos) {}

    public function listar(): Collection
    {
        return $this->permisos->todosOrdenados();
    }
}
