<?php

namespace App\Anticipos\Controllers;

use App\Anticipos\Requests\CruzarAnticipoRequest;
use App\Anticipos\Requests\RegistrarAnticipoRequest;
use App\Anticipos\Services\AnticipoService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AnticipoController extends Controller
{
    public function __construct(protected AnticipoService $anticipos) {}

    public function storeProveedor(RegistrarAnticipoRequest $peticion): JsonResponse
    {
        $anticipo = $this->anticipos->registrarAnticipoProveedor($peticion->validated());

        return response()->json(['anticipo' => $anticipo], 201);
    }

    public function storeCliente(RegistrarAnticipoRequest $peticion): JsonResponse
    {
        $anticipo = $this->anticipos->registrarAnticipoCliente($peticion->validated());

        return response()->json(['anticipo' => $anticipo], 201);
    }

    public function cruzar(CruzarAnticipoRequest $peticion): JsonResponse
    {
        $resultado = $this->anticipos->cruzar($peticion->validated());

        return response()->json($resultado, 201);
    }
}
