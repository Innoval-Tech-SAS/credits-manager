<?php

namespace App\Correo\Controllers;

use App\Correo\Enums\TipoCorreo;
use App\Correo\Models\PlantillaCorreo;
use App\Correo\Requests\ActualizarPlantillaCorreoRequest;
use App\Correo\Requests\CrearPlantillaCorreoRequest;
use App\Correo\Requests\ListarPlantillasCorreoRequest;
use App\Correo\Requests\VistaPreviaPlantillaCorreoRequest;
use App\Correo\Services\PlantillasCorreoService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PlantillaCorreoController extends Controller
{
    public function __construct(protected PlantillasCorreoService $plantillas) {}

    public function index(ListarPlantillasCorreoRequest $peticion): JsonResponse
    {
        $tipo = TipoCorreo::from($peticion->validated('tipo'));

        return response()->json(['plantillas' => $this->plantillas->listar($peticion->user(), $tipo)]);
    }

    public function store(CrearPlantillaCorreoRequest $peticion): JsonResponse
    {
        $tipo = TipoCorreo::from($peticion->validated('tipo'));
        $plantilla = $this->plantillas->crear($peticion->user(), $tipo, $peticion->validated());

        return response()->json(['plantilla' => $plantilla], 201);
    }

    public function update(ActualizarPlantillaCorreoRequest $peticion, PlantillaCorreo $plantilla): JsonResponse
    {
        return response()->json(['plantilla' => $this->plantillas->actualizar($peticion->user(), $plantilla, $peticion->validated())]);
    }

    public function destroy(Request $peticion, PlantillaCorreo $plantilla): Response
    {
        $this->plantillas->eliminar($peticion->user(), $plantilla);

        return response()->noContent();
    }

    public function marcarPredeterminada(Request $peticion, PlantillaCorreo $plantilla): JsonResponse
    {
        return response()->json(['plantilla' => $this->plantillas->marcarPredeterminada($peticion->user(), $plantilla)]);
    }

    public function vistaPrevia(VistaPreviaPlantillaCorreoRequest $peticion): JsonResponse
    {
        $tipo = TipoCorreo::from($peticion->validated('tipo'));

        return response()->json([
            'vista_previa' => $this->plantillas->vistaPrevia($tipo, $peticion->validated('asunto'), $peticion->validated('cuerpo')),
        ]);
    }
}
