<?php

namespace App\Respaldos\Controllers;

use App\Http\Controllers\Controller;
use App\Respaldos\Requests\ActualizarConfiguracionRespaldoExternoRequest;
use App\Respaldos\Services\ConfiguracionRespaldoExternoService;
use Illuminate\Http\JsonResponse;

/** Configuración de la copia externa (S3) del respaldo: solo superadmin (middleware de ruta). */
class ConfiguracionRespaldoExternoController extends Controller
{
    public function __construct(protected ConfiguracionRespaldoExternoService $configuracion) {}

    public function show(): JsonResponse
    {
        return response()->json(['configuracion' => $this->configuracion->obtener()]);
    }

    public function update(ActualizarConfiguracionRespaldoExternoRequest $peticion): JsonResponse
    {
        return response()->json([
            'configuracion' => $this->configuracion->guardar($peticion->user(), $peticion->validated()),
        ]);
    }

    public function prueba(): JsonResponse
    {
        return response()->json($this->configuracion->probar());
    }
}
