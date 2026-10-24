<?php

namespace App\Recibos\Controllers;

use App\Http\Controllers\Controller;
use App\Pagos\Models\Pago;
use App\Recibos\Enums\EstadoRecibo;
use App\Recibos\Requests\ListarRecibosRequest;
use App\Recibos\Services\ReciboCajaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ReciboCajaController extends Controller
{
    public function __construct(protected ReciboCajaService $recibos) {}

    public function listar(ListarRecibosRequest $peticion): JsonResponse
    {
        $datos = $peticion->validated();

        $recibos = $this->recibos->listar(
            [
                'tercero_id' => isset($datos['tercero_id']) ? (int) $datos['tercero_id'] : null,
                'desde' => $datos['desde'] ?? null,
                'hasta' => $datos['hasta'] ?? null,
                'estado' => isset($datos['estado']) ? EstadoRecibo::from($datos['estado']) : null,
            ],
            (int) ($datos['per_page'] ?? 10),
            (int) ($datos['page'] ?? 1),
        );

        return response()->json($recibos);
    }

    public function pdf(Pago $pago): Response
    {
        $pdf = $this->recibos->generarPdf($pago);

        return response($pdf->contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf->nombreArchivo.'.pdf"',
        ]);
    }
}
