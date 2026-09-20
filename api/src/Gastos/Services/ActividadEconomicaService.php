<?php

namespace App\Gastos\Services;

use App\Gastos\Models\ActividadEconomica;
use App\Gastos\Repositories\ActividadEconomicaRepository;
use Illuminate\Database\Eloquent\Collection;

class ActividadEconomicaService
{
    public function __construct(protected ActividadEconomicaRepository $actividades) {}

    public function listar(): Collection
    {
        return $this->actividades->todos();
    }

    public function crear(array $datos): ActividadEconomica
    {
        return $this->actividades->crear($datos);
    }

    public function editar(ActividadEconomica $actividad, array $datos): ActividadEconomica
    {
        return $this->actividades->actualizar($actividad, $datos);
    }
}
