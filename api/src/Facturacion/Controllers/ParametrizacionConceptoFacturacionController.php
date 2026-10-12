<?php

namespace App\Facturacion\Controllers;

use App\Facturacion\Enums\TipoConceptoFacturacion;
use App\Facturacion\Requests\ActualizarParametrizacionConceptoFacturacionRequest;
use App\Facturacion\Services\ParametrizacionConceptoFacturacionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParametrizacionConceptoFacturacionController extends Controller
{
    public function __construct(protected ParametrizacionConceptoFacturacionService $parametrizaciones) {}

    public function index(Request $peticion): JsonResponse
    {
        $filas = $this->parametrizaciones->listar((int) $peticion->user()->empresa_id);

        return response()->json(['parametrizaciones' => $filas->values()]);
    }

    public function update(ActualizarParametrizacionConceptoFacturacionRequest $peticion, TipoConceptoFacturacion $tipoConcepto): JsonResponse
    {
        $fila = $this->parametrizaciones->actualizar($peticion->user(), $tipoConcepto, $peticion->validated());

        return response()->json(['parametrizacion' => $fila]);
    }
}
