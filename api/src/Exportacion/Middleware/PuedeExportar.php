<?php

namespace App\Exportacion\Middleware;

use App\Exportacion\Services\AccesoExportaciones;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `exportacion:{reporte},...`: la ruta nombra la clave del reporte, no el permiso, y el permiso sale del mismo mapa que
 * usan la fuente y la descarga. Con varias claves basta una (el libro mayor se exporta como mayor o como caja y bancos).
 */
class PuedeExportar
{
    public function __construct(protected AccesoExportaciones $acceso) {}

    public function handle(Request $peticion, Closure $siguiente, string ...$reportes): Response
    {
        $usuario = $peticion->user();

        $autorizado = $usuario !== null && collect($reportes)->contains(fn (string $reporte) => $this->acceso->puede($usuario, $reporte));

        if (! $autorizado) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }

        return $siguiente($peticion);
    }
}
