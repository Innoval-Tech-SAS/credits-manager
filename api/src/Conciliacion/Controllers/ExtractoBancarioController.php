<?php

namespace App\Conciliacion\Controllers;

use App\Conciliacion\Models\CuentaBancaria;
use App\Conciliacion\Requests\CargarExtractoRequest;
use App\Conciliacion\Services\ExtractoBancarioService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ExtractoBancarioController extends Controller
{
    public function __construct(protected ExtractoBancarioService $extractos) {}

    public function index(CuentaBancaria $cuentaBancaria): JsonResponse
    {
        return response()->json(['extractos' => $this->extractos->listar($cuentaBancaria)]);
    }

    public function store(CargarExtractoRequest $peticion, CuentaBancaria $cuentaBancaria): JsonResponse
    {
        $extracto = $this->extractos->solicitarCarga($peticion->user(), $cuentaBancaria, $peticion->file('archivo'), $peticion->validated('mes_primera_fila'));

        return response()->json(['extracto' => $extracto], 202);
    }
}
