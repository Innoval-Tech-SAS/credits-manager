<?php

namespace App\Terceros\Controllers;

use App\Http\Controllers\Controller;
use App\Terceros\Constants\TerceroConstants;
use App\Terceros\Models\Tercero;
use App\Terceros\Requests\CrearTerceroRequest;
use App\Terceros\Requests\EditarTerceroRequest;
use App\Terceros\Services\TerceroService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TerceroController extends Controller
{
    public function __construct(protected TerceroService $terceros) {}

    /**
     * El permiso requerido depende de qué tipo se está listando: un asesor
     * (permiso `clientes.listar`, sin `contabilidad.listar`) solo puede ver
     * los terceros tipo cliente; listar proveedores, otros o el catálogo
     * completo sigue requiriendo `contabilidad.listar`.
     */
    public function index(Request $peticion): JsonResponse
    {
        $tipo = $peticion->string('tipo')->toString() ?: null;
        $tipo = in_array($tipo, TerceroConstants::TIPOS_FILTRO, true) ? $tipo : null;

        $modulo = $tipo === 'cliente' ? 'clientes' : 'contabilidad';

        abort_unless($peticion->user()->tienePermiso($modulo, 'listar'), 403, 'No tienes permiso para realizar esta acción.');

        $terceros = $this->terceros->listar(
            busqueda: $peticion->string('search')->toString() ?: null,
            tipo: $tipo,
            porPagina: $peticion->integer('per_page', 10),
            pagina: $peticion->integer('page', 1),
        );

        return response()->json($terceros);
    }

    public function store(CrearTerceroRequest $peticion): JsonResponse
    {
        $tercero = $this->terceros->crear($peticion->validated());

        return response()->json(['tercero' => $tercero], 201);
    }

    public function show(Tercero $tercero): JsonResponse
    {
        return response()->json(['tercero' => $tercero]);
    }

    public function update(EditarTerceroRequest $peticion, Tercero $tercero): JsonResponse
    {
        $tercero = $this->terceros->editar($tercero, $peticion->validated());

        return response()->json(['tercero' => $tercero]);
    }
}
