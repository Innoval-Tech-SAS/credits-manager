<?php

namespace App\Proveedores\Controllers;

use App\Http\Controllers\Controller;
use App\Proveedores\Models\Proveedor;
use App\Proveedores\Requests\CrearProveedorJuridicaRequest;
use App\Proveedores\Requests\CrearProveedorNaturalRequest;
use App\Proveedores\Requests\EditarProveedorJuridicaRequest;
use App\Proveedores\Requests\EditarProveedorNaturalRequest;
use App\Proveedores\Services\ProveedorService;
use App\Terceros\Enums\TipoPersona;
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

    public function storeNatural(CrearProveedorNaturalRequest $peticion): JsonResponse
    {
        $proveedor = $this->proveedores->crear(TipoPersona::Natural, $peticion->validated());

        return response()->json(['proveedor' => $proveedor->load('tercero')], 201);
    }

    public function storeJuridica(CrearProveedorJuridicaRequest $peticion): JsonResponse
    {
        $proveedor = $this->proveedores->crear(TipoPersona::Juridica, $peticion->validated());

        return response()->json(['proveedor' => $proveedor->load('tercero')], 201);
    }

    public function show(Proveedor $proveedor): JsonResponse
    {
        return response()->json(['proveedor' => $proveedor->load('tercero')]);
    }

    public function updateNatural(EditarProveedorNaturalRequest $peticion, Proveedor $proveedor): JsonResponse
    {
        abort_unless($proveedor->tercero->tipo_persona === TipoPersona::Natural, 404);

        $proveedor = $this->proveedores->editar($proveedor, $peticion->validated());

        return response()->json(['proveedor' => $proveedor->load('tercero')]);
    }

    public function updateJuridica(EditarProveedorJuridicaRequest $peticion, Proveedor $proveedor): JsonResponse
    {
        abort_unless($proveedor->tercero->tipo_persona === TipoPersona::Juridica, 404);

        $proveedor = $this->proveedores->editar($proveedor, $peticion->validated());

        return response()->json(['proveedor' => $proveedor->load('tercero')]);
    }
}
