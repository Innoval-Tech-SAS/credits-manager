<?php

namespace App\Conciliacion\Controllers;

use App\Conciliacion\Models\ExtractoBancario;
use App\Conciliacion\Models\MarcaConciliacion;
use App\Conciliacion\Models\MovimientoExtracto;
use App\Conciliacion\Requests\ConciliarMovimientoRequest;
use App\Conciliacion\Requests\DesmarcarMarcaRequest;
use App\Conciliacion\Requests\EnviarAPuenteRequest;
use App\Conciliacion\Services\MatchConciliacionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class MatchConciliacionController extends Controller
{
    public function __construct(protected MatchConciliacionService $match) {}

    public function candidatos(ExtractoBancario $extracto): JsonResponse
    {
        return response()->json($this->match->candidatos($extracto));
    }

    public function conciliar(ConciliarMovimientoRequest $peticion, MovimientoExtracto $movimiento): JsonResponse
    {
        $marca = $this->match->marcar($movimiento, (int) $peticion->validated('asiento_contable_id'), $peticion->user());

        return response()->json(['marca' => $marca], 201);
    }

    public function enviarAPuente(EnviarAPuenteRequest $peticion, MovimientoExtracto $movimiento): JsonResponse
    {
        $marca = $this->match->enviarAPuente(
            $movimiento,
            $peticion->user(),
            (int) $peticion->validated('tipo_comprobante_id'),
            $peticion->validated('observacion'),
        );

        return response()->json(['marca' => $marca], 201);
    }

    public function desmarcar(DesmarcarMarcaRequest $peticion, MarcaConciliacion $marca): JsonResponse
    {
        $reversa = $this->match->desmarcar($marca, $peticion->user(), $peticion->validated('motivo'));

        return response()->json(['marca' => $reversa]);
    }
}
