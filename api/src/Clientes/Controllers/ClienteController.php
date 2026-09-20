<?php

namespace App\Clientes\Controllers;

use App\Clientes\Models\Cliente;
use App\Clientes\Requests\CrearClienteRequest;
use App\Clientes\Requests\EditarClienteRequest;
use App\Clientes\Services\ClienteService;
use App\Creditos\Enums\EstadoCredito;
use App\Documentos\Requests\SubirArchivoAdjuntoRequest;
use App\Documentos\Services\ArchivoAdjuntoService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ClienteController extends Controller
{
    public function __construct(
        protected ClienteService $clientes,
        protected ArchivoAdjuntoService $documentos,
    ) {}

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

        return response()->json(['cliente' => $cliente->load(['tercero', 'empleador'])], 201);
    }

    public function show(Cliente $cliente): JsonResponse
    {
        return response()->json(['cliente' => $cliente->load(['tercero', 'empleador'])]);
    }

    public function update(EditarClienteRequest $peticion, Cliente $cliente): JsonResponse
    {
        $cliente = $this->clientes->editarCliente($cliente, $peticion->validated());

        return response()->json(['cliente' => $cliente->load(['tercero', 'empleador'])]);
    }

    public function destroy(Cliente $cliente): Response
    {
        $this->clientes->eliminar($cliente);

        return response()->noContent();
    }

    public function resumen(Cliente $cliente): JsonResponse
    {
        return response()->json([
            'cliente' => $cliente->load(['tercero', 'empleador']),
            ...$this->clientes->resumenCliente($cliente),
        ]);
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
