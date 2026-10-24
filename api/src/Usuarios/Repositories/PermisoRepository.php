<?php

namespace App\Usuarios\Repositories;

use App\Core\Base\BaseRepository;
use App\Usuarios\Models\Permiso;
use Illuminate\Database\Eloquent\Collection;

class PermisoRepository extends BaseRepository
{
    public function __construct(Permiso $permiso)
    {
        parent::__construct($permiso);
    }

    public function todosOrdenados(): Collection
    {
        return $this->consulta()->orderBy('modulo')->orderBy('accion')->get();
    }
}
