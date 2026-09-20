<?php

namespace App\Proveedores\Services;

use App\Proveedores\Models\Proveedor;
use App\Proveedores\Repositories\ProveedorRepository;
use App\Terceros\Repositories\TerceroRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProveedorService
{
    public function __construct(
        protected ProveedorRepository $proveedores,
        protected TerceroRepository $terceros,
    ) {}

    public function listar(?string $busqueda, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->proveedores->paginar($busqueda, max(1, min(100, $porPagina)), max(1, $pagina));
    }

    public function crear(array $datos): Proveedor
    {
        return DB::transaction(function () use ($datos) {
            $tercero = $this->terceros->obtenerOCrear($this->datosTercero($datos));

            $proveedor = $this->proveedores->crear(['tercero_id' => $tercero->id, 'activo' => true]);

            return $proveedor->setAttribute('tercero_reutilizado', ! $tercero->wasRecentlyCreated);
        });
    }

    public function editar(Proveedor $proveedor, array $datos): Proveedor
    {
        return DB::transaction(function () use ($proveedor, $datos) {
            $this->terceros->actualizar($proveedor->tercero, $this->datosTercero($datos));

            return $this->proveedores->actualizar($proveedor, [
                ...(array_key_exists('activo', $datos) ? ['activo' => $datos['activo']] : []),
            ]);
        });
    }

    private function datosTercero(array $datos): array
    {
        return [
            'nombre' => $datos['nombre'],
            'tipo_documento' => $datos['tipo_documento'],
            'numero_documento' => $datos['numero_documento'],
            'direccion' => $datos['direccion'] ?? null,
            'barrio' => $datos['barrio'] ?? null,
            'ciudad' => $datos['ciudad'] ?? null,
            'celular' => $datos['celular'] ?? null,
            'telefono_fijo' => $datos['telefono_fijo'] ?? null,
            'correo' => $datos['correo'] ?? null,
            'regimen_tributario' => $datos['regimen_tributario'] ?? null,
            'tipo_persona' => $datos['tipo_persona'] ?? null,
            'autorretenedor' => $datos['autorretenedor'] ?? false,
            'responsable_iva' => $datos['responsable_iva'] ?? false,
            'actividad_economica_id' => $datos['actividad_economica_id'] ?? null,
        ];
    }
}
