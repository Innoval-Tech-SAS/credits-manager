<?php

namespace App\Core\Base;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository
{
    public function __construct(protected Model $modelo) {}

    public function consulta(): Builder
    {
        return $this->modelo->newQuery();
    }

    public function todos(): Collection
    {
        return $this->consulta()->get();
    }

    public function buscarPorId(int|string $id): ?Model
    {
        return $this->consulta()->find($id);
    }

    public function buscarPorIdOFallar(int|string $id): Model
    {
        return $this->consulta()->findOrFail($id);
    }

    public function crear(array $datos): Model
    {
        $modelo = $this->modelo->newInstance($datos);
        $modelo->save();

        return $modelo;
    }

    public function actualizar(Model $modelo, array $datos): Model
    {
        $modelo->fill($datos);
        $modelo->save();

        return $modelo;
    }

    public function eliminar(Model $modelo): bool
    {
        return (bool) $modelo->delete();
    }

    public function restaurar(Model $modelo): bool
    {
        return (bool) $modelo->restore();
    }

    public function eliminarPermanentemente(Model $modelo): bool
    {
        return (bool) $modelo->forceDelete();
    }
}
