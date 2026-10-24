<?php

namespace App\Contabilidad\Controllers;

use App\Contabilidad\Enums\EventoContable;
use App\Contabilidad\Requests\GuardarReglaContabilizacionRequest;
use App\Contabilidad\Services\ReglaContabilizacionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ReglaContabilizacionController extends Controller
{
    public function __construct(protected ReglaContabilizacionService $reglas) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'eventos' => array_map(fn (EventoContable $evento) => $evento->value, EventoContable::cases()),
            'reglas' => $this->reglas->listar(),
        ]);
    }

    public function store(GuardarReglaContabilizacionRequest $peticion): JsonResponse
    {
        $datos = $peticion->validated();

        $regla = $this->reglas->guardar(
            EventoContable::from($datos['evento']),
            $datos['cuenta_debito_id'],
            $datos['cuenta_credito_id'],
            $datos['tipo_comprobante_id'],
        );

        return response()->json(['regla' => $regla], 201);
    }
}
