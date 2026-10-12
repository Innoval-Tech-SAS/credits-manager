<?php

namespace App\Auth\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Como `permiso`, para catálogos de consulta que sirven a varias operaciones de módulos distintos: deja pasar a quien
 * tenga al menos uno de los permisos "modulo.accion" dados (`algun_permiso:clientes.crear,metas.configurar`).
 */
class TieneAlgunPermiso
{
    public function handle(Request $peticion, Closure $siguiente, string ...$permisos): Response
    {
        $usuario = $peticion->user();

        $autorizado = $usuario !== null && collect($permisos)->contains(function (string $clave) use ($usuario) {
            [$modulo, $accion] = explode('.', $clave, 2);

            return $usuario->tienePermiso($modulo, $accion);
        });

        if (! $autorizado) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }

        return $siguiente($peticion);
    }
}
