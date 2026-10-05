<?php

namespace App\Correo\Controllers;

use App\Correo\Requests\ActualizarConfiguracionCorreoRequest;
use App\Correo\Requests\EnviarPruebaCorreoRequest;
use App\Correo\Requests\VistaPreviaCorreoRequest;
use App\Correo\Services\ConfiguracionCorreoService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfiguracionCorreoController extends Controller
{
    public function __construct(protected ConfiguracionCorreoService $configuracion) {}

    public function show(Request $peticion): JsonResponse
    {
        return response()->json(['configuracion' => $this->configuracion->obtener($peticion->user())]);
    }

    public function update(ActualizarConfiguracionCorreoRequest $peticion): JsonResponse
    {
        return response()->json([
            'configuracion' => $this->configuracion->guardar($peticion->user(), $peticion->validated()),
        ]);
    }

    public function prueba(EnviarPruebaCorreoRequest $peticion): JsonResponse
    {
        return response()->json($this->configuracion->enviarPrueba($peticion->user(), $peticion->validated('destinatario')));
    }

    public function vistaPrevia(VistaPreviaCorreoRequest $peticion): JsonResponse
    {
        return response()->json([
            'vista_previa' => $this->configuracion->vistaPrevia($peticion->validated('asunto'), $peticion->validated('cuerpo')),
        ]);
    }
}
