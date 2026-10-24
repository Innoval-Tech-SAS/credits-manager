<?php

namespace App\Conciliacion\Controllers;

use App\Conciliacion\Models\CuentaBancaria;
use App\Conciliacion\Requests\CrearCuentaBancariaRequest;
use App\Conciliacion\Requests\EditarCuentaBancariaRequest;
use App\Conciliacion\Services\CuentaBancariaService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CuentaBancariaController extends Controller
{
    public function __construct(protected CuentaBancariaService $cuentasBancarias) {}

    public function index(): JsonResponse
    {
        return response()->json(['cuentas_bancarias' => $this->cuentasBancarias->listar()]);
    }

    public function store(CrearCuentaBancariaRequest $peticion): JsonResponse
    {
        return response()->json(['cuenta_bancaria' => $this->cuentasBancarias->crear($peticion->validated())], 201);
    }

    public function update(EditarCuentaBancariaRequest $peticion, CuentaBancaria $cuentaBancaria): JsonResponse
    {
        return response()->json(['cuenta_bancaria' => $this->cuentasBancarias->editar($cuentaBancaria, $peticion->validated())]);
    }
}
