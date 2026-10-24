<?php

namespace App\Exportacion\Controllers;

use App\Exportacion\Services\ExportacionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Exportaciones del propio usuario (todas las empresas comparten estos endpoints; solo ve las suyas). */
class ExportacionController extends Controller
{
    public function __construct(protected ExportacionService $exportaciones) {}

    public function index(Request $peticion): JsonResponse
    {
        return response()->json($this->exportaciones->listar(
            $peticion->user(),
            $peticion->integer('per_page', 10),
            $peticion->integer('page', 1),
        ));
    }

    public function descargar(Request $peticion, int $exportacion): StreamedResponse
    {
        return $this->exportaciones->descargar($exportacion, $peticion->user());
    }
}
