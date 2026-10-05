<?php

namespace App\Clientes\Controllers;

use App\Clientes\Models\Cliente;
use App\Clientes\Requests\CrearClienteRequest;
use App\Clientes\Requests\EditarClienteRequest;
use App\Clientes\Requests\ExportarClientesRequest;
use App\Clientes\Requests\ReasignarAsesorRequest;
use App\Clientes\Services\AsignacionAsesorService;
use App\Clientes\Services\ClienteService;
use App\Creditos\Enums\EstadoCredito;
use App\Documentos\Requests\SubirArchivoAdjuntoRequest;
use App\Documentos\Services\ArchivoAdjuntoService;
use App\Exportacion\Enums\FormatoExportacion;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ClienteController extends Controller
{
    public function __construct(
        protected ClienteService $clientes,
        protected AsignacionAsesorService $asignaciones,
        protected ArchivoAdjuntoService $documentos,
    ) {}

    public function exportar(ExportarClientesRequest $peticion): JsonResponse
    {
        $estado = $peticion->validated('estado');
        $estadoCredito = $peticion->validated('estado_credito');

        $exportacion = $this->clientes->solicitarExportacion(
            $peticion->user(),
            $peticion->validated('search'),
            $estado === null ? null : $estado === 'activo',
            $estadoCredito ? EstadoCredito::from($estadoCredito) : null,
            FormatoExportacion::from($peticion->validated('formato')),
        );

        return response()->json(['exportacion' => $exportacion], 202);
    }

    public function index(Request $peticion): JsonResponse
    {
        $estado = $peticion->string('estado')->toString();
        $estadoCredito = $peticion->string('estado_credito')->toString();

        $clientes = $this->clientes->listarClientes(
            busqueda: $peticion->string('search')->toString(),
            columna: $peticion->string('sort', 'nombre')->toString(),
            direccion: $peticion->string('direccion', 'asc')->toString(),
            porPagina: $peticion->integer('per_page', 10),
            pagina: $peticion->integer('page', 1),
            activo: $estado === '' ? null : $estado === 'activo',
            estadoCredito: $estadoCredito !== '' ? EstadoCredito::from($estadoCredito) : null,
        );

        return response()->json($clientes);
    }

    public function store(CrearClienteRequest $peticion): JsonResponse
    {
        $cliente = $this->clientes->crearCliente($peticion->validated());

        return response()->json(['cliente' => $cliente->load(['tercero', 'empleador', 'asesor:id,nombre'])], 201);
    }

    public function show(Cliente $cliente): JsonResponse
    {
        return response()->json(['cliente' => $cliente->load(['tercero', 'empleador', 'asesor:id,nombre'])]);
    }

    public function update(EditarClienteRequest $peticion, Cliente $cliente): JsonResponse
    {
        $cliente = $this->clientes->editarCliente($cliente, $peticion->validated());

        return response()->json(['cliente' => $cliente->load(['tercero', 'empleador', 'asesor:id,nombre'])]);
    }

    public function destroy(Cliente $cliente): Response
    {
        $this->clientes->eliminar($cliente);

        return response()->noContent();
    }

    public function resumen(Cliente $cliente): JsonResponse
    {
        return response()->json([
            'cliente' => $cliente->load(['tercero', 'empleador', 'asesor:id,nombre']),
            ...$this->clientes->resumenCliente($cliente),
        ]);
    }

    public function asesoresAsignables(): JsonResponse
    {
        return response()->json(['asesores' => $this->asignaciones->asesoresAsignables()]);
    }

    public function reasignarAsesor(ReasignarAsesorRequest $peticion, Cliente $cliente): JsonResponse
    {
        $cliente = $this->asignaciones->reasignar($cliente, $peticion->integer('asesor_id'), $peticion->string('motivo')->toString());

        return response()->json(['cliente' => $cliente->load(['tercero', 'empleador', 'asesor:id,nombre'])]);
    }

    public function historialAsesores(Cliente $cliente): JsonResponse
    {
        return response()->json(['historial' => $this->asignaciones->historial($cliente)]);
    }

    public function documentos(Cliente $cliente): JsonResponse
    {
        return response()->json(['documentos' => $this->documentos->listarPara($cliente)]);
    }

    public function subirDocumento(SubirArchivoAdjuntoRequest $peticion, Cliente $cliente): JsonResponse
    {
        $documento = $this->documentos->subir($cliente, $peticion->file('archivo'));

        return response()->json(['documento' => $documento], 201);
    }
}
