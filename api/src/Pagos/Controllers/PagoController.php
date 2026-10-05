<?php

namespace App\Pagos\Controllers;

use App\Core\Dinero\Dinero;
use App\Creditos\Models\Credito;
use App\Exportacion\Enums\FormatoExportacion;
use App\Http\Controllers\Controller;
use App\Pagos\Enums\FormaPago;
use App\Pagos\Requests\CrearPagoRequest;
use App\Pagos\Requests\ExportarPagosRequest;
use App\Pagos\Requests\LiquidarCreditoRequest;
use App\Pagos\Services\PagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PagoController extends Controller
{
    public function __construct(protected PagoService $pagoService) {}

    public function listar(Request $peticion): JsonResponse
    {
        $pagos = $this->pagoService->listar(
            busqueda: $peticion->string('search')->toString(),
            porPagina: $peticion->integer('per_page', 10),
            pagina: $peticion->integer('page', 1),
        );

        return response()->json($pagos);
    }

    public function exportar(ExportarPagosRequest $peticion): JsonResponse
    {
        $exportacion = $this->pagoService->solicitarExportacion(
            $peticion->user(),
            $peticion->validated('search'),
            FormatoExportacion::from($peticion->validated('formato')),
        );

        return response()->json(['exportacion' => $exportacion], 202);
    }

    public function index(Credito $credito): JsonResponse
    {
        return response()->json(['pagos' => $this->pagoService->listarPorCredito($credito->id, 'desc')]);
    }

    public function store(CrearPagoRequest $peticion, Credito $credito): JsonResponse
    {
        $datos = $peticion->validated();

        $resultado = $this->pagoService->registrarPago(
            $credito,
            Dinero::desdePesos($datos['valor_pago']),
            FormaPago::from($datos['forma_pago']),
            $datos['observacion'] ?? null,
            Auth::id(),
        );

        return response()->json([
            'pago' => $resultado['pago'],
            'sobrante' => $resultado['sobrante'],
        ], 201);
    }

    public function liquidacion(Credito $credito): JsonResponse
    {
        return response()->json($this->pagoService->calcularLiquidacion($credito));
    }

    public function liquidar(LiquidarCreditoRequest $peticion, Credito $credito): JsonResponse
    {
        $datos = $peticion->validated();

        $resultado = $this->pagoService->liquidarCredito(
            $credito,
            FormaPago::from($datos['forma_pago']),
            $datos['observacion'] ?? null,
            Auth::id(),
        );

        return response()->json($resultado, 201);
    }
}
