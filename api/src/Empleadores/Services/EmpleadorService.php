<?php

namespace App\Empleadores\Services;

use App\Empleadores\Models\Empleador;
use App\Empleadores\Repositories\EmpleadorRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class EmpleadorService
{
    public function __construct(protected EmpleadorRepository $empleadores) {}

    public function listarEmpleadores(?string $busqueda, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->empleadores->paginar($busqueda, max(1, min(100, $porPagina)), max(1, $pagina));
    }

    public function crear(array $datos): Empleador
    {
        return $this->empleadores->crear([
            'nombre' => $datos['nombre'],
            'nit' => $datos['nit'] ?? null,
            'direccion' => $datos['direccion'] ?? null,
            'telefono' => $datos['telefono'] ?? null,
        ]);
    }

    public function editar(Empleador $empleador, array $datos): Empleador
    {
        return $this->empleadores->actualizar($empleador, [
            'nombre' => $datos['nombre'],
            'nit' => $datos['nit'] ?? null,
            'direccion' => $datos['direccion'] ?? null,
            'telefono' => $datos['telefono'] ?? null,
        ]);
    }

    public function eliminar(Empleador $empleador): bool
    {
        if ($empleador->clientes()->exists()) {
            throw ValidationException::withMessages([
                'empleador' => __('validation.custom.empleador.tiene_clientes_asignados'),
            ]);
        }

        return $this->empleadores->eliminar($empleador);
    }
}
