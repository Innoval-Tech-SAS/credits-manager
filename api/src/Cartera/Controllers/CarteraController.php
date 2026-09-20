<?php

namespace App\Cartera\Controllers;

use App\Cartera\Services\CarteraService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CarteraController extends Controller
{
    public function __construct(protected CarteraService $cartera) {}

    public function index(): JsonResponse
    {
        return response()->json($this->cartera->resumen());
    }

    public function reporteEmpleador(Request $peticion): JsonResponse
    {
        return response()->json($this->cartera->reporteEmpleador($peticion->integer('empleador_id') ?: null));
    }
}
