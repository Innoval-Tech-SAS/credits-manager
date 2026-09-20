<?php

namespace App\Proveedores\Controllers;

use App\Http\Controllers\Controller;
use App\Proveedores\Models\Proveedor;
use App\Proveedores\Requests\CrearProveedorRequest;
use App\Proveedores\Requests\EditarProveedorRequest;
use App\Proveedores\Services\ProveedorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    public function __construct(protected ProveedorService $proveedores) {}

    public function index(Request $peticion): JsonResponse
    {
        $proveedores = $this->proveedores->listar(
            $peticion->string('search')->toString() ?: null,
            $peticion->integer('per_page', 10),
            $peticion->integer('page', 1),
        );

        return response()->json($proveedores);
    }

    public function store(CrearProveedorRequest $peticion): JsonResponse
    {
        $proveedor = $this->proveedores->crear($peticion->validated());

        return response()->json(['proveedor' => $proveedor->load('tercero')], 201);
    }

    public function show(Proveedor $proveedor): JsonResponse
    {
        return response()->json(['proveedor' => $proveedor->load('tercero')]);
    }

    public function update(EditarProveedorRequest $peticion, Proveedor $proveedor): JsonResponse
    {
        $proveedor = $this->proveedores->editar($proveedor, $peticion->validated());

        return response()->json(['proveedor' => $proveedor->load('tercero')]);
    }
}
