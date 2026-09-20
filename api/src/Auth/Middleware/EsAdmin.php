<?php

namespace App\Auth\Middleware;

use App\Usuarios\Constants\UsuarioConstants;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EsAdmin
{
    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $usuario = $peticion->user();

        if (! $usuario || $usuario->rol?->nombre !== UsuarioConstants::ROL_ADMIN) {
            abort(403, 'No tienes permisos de administrador.');
        }

        return $siguiente($peticion);
    }
}
