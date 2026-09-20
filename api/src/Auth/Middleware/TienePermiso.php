<?php

namespace App\Auth\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TienePermiso
{
    public function handle(Request $peticion, Closure $siguiente, string $modulo, string $accion): Response
    {
        $usuario = $peticion->user();

        if (! $usuario || ! $usuario->tienePermiso($modulo, $accion)) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }

        return $siguiente($peticion);
    }
}
