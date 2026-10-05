<?php

namespace App\Importacion\Controllers;

use App\Http\Controllers\Controller;
use App\Importacion\Enums\TipoImportacion;
use App\Importacion\Requests\DecidirImportacionRequest;
use App\Importacion\Requests\SolicitarImportacionRequest;
use App\Importacion\Services\ImportacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportacionController extends Controller
{
    public function __construct(protected ImportacionService $importaciones) {}

    public function index(Request $peticion): JsonResponse
    {
        return response()->json($this->importaciones->listar(
            $peticion->user(),
            $peticion->integer('per_page', 10),
            $peticion->integer('page', 1),
        ));
    }

    public function show(Request $peticion, int $importacion): JsonResponse
    {
        $registro = $this->importaciones->mostrar($importacion, $peticion->user());

        return response()->json([
            'importacion' => $registro,
            'decisiones' => $this->importaciones->decisionesDeImportacion($registro),
        ]);
    }

    public function store(SolicitarImportacionRequest $peticion): JsonResponse
    {
        $datos = $peticion->datos();

        $importacion = $this->importaciones->solicitar(
            $peticion->user(),
            $datos['tipo'],
            $datos['archivo'],
            $datos['crear_empleadores'],
            $datos['generar_asiento'],
            $datos['cuenta_contrapartida_id'],
        );

        return response()->json(['importacion' => $importacion], 202);
    }

    public function decidir(DecidirImportacionRequest $peticion, int $importacion): JsonResponse
    {
        $registro = $this->importaciones->mostrar($importacion, $peticion->user());
        $decisiones = $peticion->decisiones();

        return response()->json(['importacion' => $this->importaciones->decidir(
            $registro,
            $peticion->user(),
            $decisiones['crear_empleadores'],
            $decisiones['generar_asiento'],
        )]);
    }

    public function confirmar(Request $peticion, int $importacion): JsonResponse
    {
        $registro = $this->importaciones->mostrar($importacion, $peticion->user());

        return response()->json(['importacion' => $this->importaciones->confirmar($registro, $peticion->user())], 202);
    }

    public function cancelar(Request $peticion, int $importacion): JsonResponse
    {
        $registro = $this->importaciones->mostrar($importacion, $peticion->user());

        return response()->json(['importacion' => $this->importaciones->cancelar($registro, $peticion->user())]);
    }

    public function errores(Request $peticion, int $importacion): JsonResponse
    {
        $registro = $this->importaciones->mostrar($importacion, $peticion->user());

        return response()->json($this->importaciones->errores(
            $registro,
            $peticion->integer('per_page', 20),
            $peticion->integer('page', 1),
        ));
    }

    public function reporte(Request $peticion, int $importacion): StreamedResponse
    {
        return $this->importaciones->reporte($this->importaciones->mostrar($importacion, $peticion->user()), $peticion->user());
    }

    public function guia(string $tipo): StreamedResponse
    {
        return $this->importaciones->guia(TipoImportacion::from($tipo));
    }
}
