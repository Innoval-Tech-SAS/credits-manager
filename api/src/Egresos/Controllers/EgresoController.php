<?php

namespace App\Egresos\Controllers;

use App\Egresos\Models\Egreso;
use App\Egresos\Requests\CrearEgresoRequest;
use App\Egresos\Requests\ListarEgresosRequest;
use App\Egresos\Requests\ReversarEgresoRequest;
use App\Egresos\Services\EgresoService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class EgresoController extends Controller
{
    public function __construct(protected EgresoService $egresos) {}

    public function index(ListarEgresosRequest $peticion): JsonResponse
    {
        $egresos = $this->egresos->listar(
            $peticion->integer('tercero_id') ?: null,
            $peticion->validated('desde'),
            $peticion->validated('hasta'),
            $peticion->integer('per_page', 10),
            $peticion->integer('page', 1),
        );

        return response()->json($egresos);
    }

    public function store(CrearEgresoRequest $peticion): JsonResponse
    {
        $egreso = $this->egresos->registrar($peticion->validated(), $peticion->file('soporte'));

        return response()->json(['egreso' => $egreso], 201);
    }

    public function reversar(ReversarEgresoRequest $peticion, Egreso $egreso): JsonResponse
    {
        $reversa = $this->egresos->reversar($egreso, $peticion->validated('motivo'), Auth::id());

        return response()->json(['reversa' => $reversa]);
    }
}
