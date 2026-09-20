<?php

namespace App\Auth\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EsSuperAdmin
{
    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $usuario = $peticion->user();

        if (! $usuario || ! $usuario->es_superadmin) {
            abort(403, 'No tienes permisos de super-administrador.');
        }

        return $siguiente($peticion);
    }
}
