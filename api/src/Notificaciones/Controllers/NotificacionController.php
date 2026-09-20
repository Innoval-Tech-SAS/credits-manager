<?php

namespace App\Notificaciones\Controllers;

use App\Http\Controllers\Controller;
use App\Notificaciones\Services\NotificacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NotificacionController extends Controller
{
    public function __construct(protected NotificacionService $notificaciones) {}

    public function index(Request $peticion): JsonResponse
    {
        return response()->json($this->notificaciones->listar($peticion->user()));
    }

    public function leer(Request $peticion, int $notificacion): JsonResponse
    {
        return response()->json(['notificacion' => $this->notificaciones->marcarLeida($notificacion, $peticion->user())]);
    }

    public function leerTodas(Request $peticion): Response
    {
        $this->notificaciones->marcarTodasLeidas($peticion->user());

        return response()->noContent();
    }
}
