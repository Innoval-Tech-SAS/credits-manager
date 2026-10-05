<?php

namespace App\Gastos\Controllers;

use App\Exportacion\Enums\FormatoExportacion;
use App\Gastos\Models\Gasto;
use App\Gastos\Requests\CrearGastoRequest;
use App\Gastos\Requests\ExportarGastosRequest;
use App\Gastos\Services\GastoService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GastoController extends Controller
{
    public function __construct(protected GastoService $gastos) {}

    public function index(Request $peticion): JsonResponse
    {
        $gastos = $this->gastos->listar(
            $peticion->string('search')->toString(),
            $peticion->integer('per_page', 10),
            $peticion->integer('page', 1),
        );

        return response()->json($gastos);
    }

    public function exportar(ExportarGastosRequest $peticion): JsonResponse
    {
        $exportacion = $this->gastos->solicitarExportacion(
            $peticion->user(),
            $peticion->validated('search'),
            FormatoExportacion::from($peticion->validated('formato')),
        );

        return response()->json(['exportacion' => $exportacion], 202);
    }

    public function store(CrearGastoRequest $peticion): JsonResponse
    {
        return response()->json(['gasto' => $this->gastos->causar($peticion->validated())], 201);
    }

    public function show(Gasto $gasto): JsonResponse
    {
        return response()->json(['gasto' => $this->gastos->mostrar($gasto)]);
    }

    public function destroy(Gasto $gasto): Response
    {
        $this->gastos->anular($gasto);

        return response()->noContent();
    }
}
