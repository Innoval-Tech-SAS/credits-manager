<?php

namespace App\Correo\Controllers;

use App\Clientes\Models\Cliente;
use App\Correo\Requests\EnviarRecordatorioCorreoRequest;
use App\Correo\Requests\VistaPreviaRecordatorioCorreoRequest;
use App\Correo\Services\EnvioRecordatorioService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class RecordatorioCorreoController extends Controller
{
    public function __construct(protected EnvioRecordatorioService $recordatorios) {}

    public function historial(Cliente $cliente): JsonResponse
    {
        return response()->json($this->recordatorios->historial($cliente));
    }

    public function vistaPrevia(Cliente $cliente, VistaPreviaRecordatorioCorreoRequest $peticion): JsonResponse
    {
        return response()->json([
            'vista_previa' => $this->recordatorios->vistaPrevia(
                $peticion->user(),
                $cliente,
                $peticion->validated('cuota_id'),
                $peticion->validated('asunto'),
                $peticion->validated('cuerpo'),
            ),
        ]);
    }

    public function enviar(Cliente $cliente, EnviarRecordatorioCorreoRequest $peticion): JsonResponse
    {
        $envio = $this->recordatorios->solicitar($peticion->user(), $cliente, $peticion->validated('cuota_id'));

        return response()->json(['envio' => $this->recordatorios->representar($envio)], 202);
    }
}
