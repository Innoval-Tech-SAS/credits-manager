<?php

namespace App\Metas\Controllers;

use App\Http\Controllers\Controller;
use App\Metas\Constants\MetasConstants;
use App\Metas\Models\AlertaMeta;
use App\Metas\Services\AlertaMetasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertaMetaController extends Controller
{
    public function __construct(protected AlertaMetasService $alertas) {}

    public function index(Request $peticion): JsonResponse
    {
        $alertas = $this->alertas->listar($peticion->user(), $peticion->user()->tienePermiso(MetasConstants::MODULO, 'listar'));

        return response()->json(['alertas' => $alertas->map(fn (AlertaMeta $alerta) => [
            'id' => $alerta->id,
            'meta_id' => $alerta->meta_id,
            'tipo' => $alerta->meta?->tipo,
            'periodo' => $alerta->periodo,
            'estado' => $alerta->estado,
            'usuario_id' => $alerta->usuario_id,
            'asesor' => $alerta->usuario?->nombre,
            'generada_en' => $alerta->generada_en,
            'vista_en' => $alerta->vista_en,
        ])->values()]);
    }

    public function marcarVista(Request $peticion, AlertaMeta $alerta): JsonResponse
    {
        $alerta = $this->alertas->marcarVista($peticion->user(), $alerta, $peticion->user()->tienePermiso(MetasConstants::MODULO, 'listar'));

        return response()->json(['alerta' => ['id' => $alerta->id, 'vista_en' => $alerta->vista_en]]);
    }
}
