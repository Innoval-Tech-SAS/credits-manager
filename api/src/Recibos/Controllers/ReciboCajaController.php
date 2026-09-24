<?php

namespace App\Recibos\Controllers;

use App\Http\Controllers\Controller;
use App\Pagos\Models\Pago;
use App\Recibos\Services\ReciboCajaService;
use Illuminate\Http\Response;

class ReciboCajaController extends Controller
{
    public function __construct(protected ReciboCajaService $recibos) {}

    public function pdf(Pago $pago): Response
    {
        $pdf = $this->recibos->generarPdf($pago);

        return response($pdf->contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf->nombreArchivo.'.pdf"',
        ]);
    }
}
