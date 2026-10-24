<?php

namespace App\Geografia\Controllers;

use App\Geografia\Services\PaisService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaisController extends Controller
{
    public function __construct(protected PaisService $paises) {}

    public function index(Request $peticion): JsonResponse
    {
        return response()->json(['paises' => $this->paises->listar(! $peticion->user()->es_superadmin)]);
    }
}
