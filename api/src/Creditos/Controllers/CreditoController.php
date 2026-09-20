<?php

namespace App\Creditos\Controllers;

use App\Creditos\Enums\EstadoCredito;
use App\Creditos\Models\Credito;
use App\Creditos\Requests\CrearCreditoRequest;
use App\Creditos\Services\CreditoService;
use App\Creditos\Services\CuotaService;
use App\Http\Controllers\Controller;
use App\Pagos\Services\PagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CreditoController extends Controller
{
    public function __construct(
        protected CreditoService $creditos,
        protected CuotaService $cuotas,
        protected PagoService $pagos,
    ) {}

    public function index(Request $peticion): JsonResponse
    {
        $estado = $peticion->string('estado')->toString();

        $creditos = $this->creditos->listarCreditos(
            busqueda: $peticion->string('search')->toString(),
            porPagina: $peticion->integer('per_page', 10),
            pagina: $peticion->integer('page', 1),
            clienteId: $peticion->integer('cliente_id') ?: null,
            estado: $estado ? EstadoCredito::from($estado) : null,
        );

        return response()->json($creditos);
    }

    public function store(CrearCreditoRequest $peticion): JsonResponse
    {
        $credito = $this->creditos->crearCredito($peticion->validated());

        return response()->json(['credito' => $credito], 201);
    }

    public function show(Credito $credito): JsonResponse
    {
        $credito = $this->creditos->mostrarCredito($credito);

        return response()->json([
            'credito' => $credito,
            'saldo' => $this->cuotas->saldoCredito($credito->id),
            'cartera' => $this->cuotas->estadoCartera($credito->id),
            'pagos' => $this->pagos->listarPorCredito($credito->id, 'desc'),
            'resumen_cuotas' => $this->cuotas->resumenAlDia($credito->id, $credito->valor_prestamo),
        ]);
    }

    public function estadoCuenta(Credito $credito): JsonResponse
    {
        $credito = $this->creditos->mostrarCredito($credito);

        return response()->json([
            'credito' => $credito,
            'saldo' => $this->cuotas->saldoCredito($credito->id),
            'pagos' => $this->pagos->listarPorCredito($credito->id, 'asc'),
            'resumen_cuotas' => $this->cuotas->resumenAlDia($credito->id, $credito->valor_prestamo),
        ]);
    }

    public function aprobar(Credito $credito): JsonResponse
    {
        return response()->json(['credito' => $this->creditos->aprobar($credito)]);
    }

    public function desembolsar(Credito $credito): JsonResponse
    {
        return response()->json(['credito' => $this->creditos->desembolsar($credito)]);
    }

    public function cancelar(Credito $credito): JsonResponse
    {
        return response()->json(['credito' => $this->creditos->cancelar($credito)]);
    }

    public function destroy(Credito $credito): Response
    {
        $this->creditos->eliminar($credito);

        return response()->noContent();
    }
}
