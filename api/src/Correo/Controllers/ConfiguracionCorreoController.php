<?php

namespace App\Correo\Controllers;

use App\Correo\Enums\TipoCorreo;
use App\Correo\Requests\ActualizarConfiguracionCorreoRequest;
use App\Correo\Requests\EnviarPruebaCorreoRequest;
use App\Correo\Services\ConfiguracionCorreoService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfiguracionCorreoController extends Controller
{
    public function __construct(protected ConfiguracionCorreoService $configuracion) {}

    public function show(Request $peticion, TipoCorreo $tipo): JsonResponse
    {
        return response()->json(['configuracion' => $this->configuracion->obtener($peticion->user(), $tipo)]);
    }

    public function update(ActualizarConfiguracionCorreoRequest $peticion, TipoCorreo $tipo): JsonResponse
    {
        return response()->json([
            'configuracion' => $this->configuracion->guardar($peticion->user(), $tipo, $peticion->validated()),
        ]);
    }

    public function prueba(EnviarPruebaCorreoRequest $peticion, TipoCorreo $tipo): JsonResponse
    {
        return response()->json($this->configuracion->enviarPrueba($peticion->user(), $tipo, $peticion->validated('destinatario')));
    }
}
