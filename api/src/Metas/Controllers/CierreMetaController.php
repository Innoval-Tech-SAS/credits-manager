<?php

namespace App\Metas\Controllers;

use App\Http\Controllers\Controller;
use App\Metas\Data\PeriodoMeta;
use App\Metas\Enums\TipoMeta;
use App\Metas\Models\MetaCierre;
use App\Metas\Repositories\CierreMetaRepository;
use App\Metas\Requests\ListarCierresMetasRequest;
use App\Metas\Requests\RecalcularCierreMetaRequest;
use App\Metas\Services\CierreMetasService;
use Illuminate\Http\JsonResponse;

class CierreMetaController extends Controller
{
    public function __construct(
        protected CierreMetasService $cierres,
        protected CierreMetaRepository $repositorio,
    ) {}

    public function index(ListarCierresMetasRequest $peticion): JsonResponse
    {
        $historial = $this->repositorio->historial(
            (int) $peticion->user()->empresa_id,
            $peticion->validated('periodo'),
            $peticion->validated('tipo'),
        );

        return response()->json(['cierres' => $historial->map(fn (MetaCierre $cierre) => $this->representar($cierre))->values()]);
    }

    public function recalcular(RecalcularCierreMetaRequest $peticion): JsonResponse
    {
        $cierres = $this->cierres->recalcular(
            $peticion->user(),
            PeriodoMeta::desde($peticion->validated('periodo')),
            TipoMeta::from($peticion->validated('tipo')),
            $peticion->validated('motivo'),
        );

        return response()->json(['cierres' => $cierres], 201);
    }

    /** @return array<string, mixed> */
    private function representar(MetaCierre $cierre): array
    {
        return [
            'id' => $cierre->id,
            'periodo' => $cierre->periodo,
            'tipo' => $cierre->tipo,
            'asesor_id' => $cierre->asesor_id,
            'usuario' => $cierre->usuario?->nombre,
            'cierre_anterior_id' => $cierre->cierre_anterior_id,
            'objetivo' => $cierre->objetivo,
            'ejecutado' => $cierre->ejecutado,
            'porcentaje' => $cierre->porcentaje,
            'estado' => $cierre->estado,
            'motivo' => $cierre->motivo,
            'cerrado_en' => $cierre->cerrado_en,
        ];
    }
}
