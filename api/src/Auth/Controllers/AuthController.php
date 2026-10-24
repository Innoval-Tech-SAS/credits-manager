<?php

namespace App\Auth\Controllers;

use App\Auth\Requests\CambiarClavePropiaRequest;
use App\Auth\Requests\LoginRequest;
use App\Auth\Services\AuthService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function __construct(protected AuthService $autenticacion) {}

    public function login(LoginRequest $peticion): JsonResponse
    {
        $datos = $peticion->validated();

        $this->autenticacion->iniciarSesion($peticion, $datos['usuario'], $datos['clave']);

        return response()->json([
            'usuario' => $peticion->user()->load(['rol.permisos', 'permisosExtra']),
        ]);
    }

    public function logout(Request $peticion): Response
    {
        $this->autenticacion->cerrarSesion($peticion);

        return response()->noContent();
    }

    public function usuarioActual(Request $peticion): JsonResponse
    {
        return response()->json([
            'usuario' => $peticion->user()->load(['rol.permisos', 'permisosExtra']),
        ]);
    }

    public function cambiarClave(CambiarClavePropiaRequest $peticion): Response
    {
        $datos = $peticion->validated();

        $this->autenticacion->cambiarClavePropia($peticion->user(), $datos['clave_actual'], $datos['clave_nueva']);

        return response()->noContent();
    }
}
