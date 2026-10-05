<?php

namespace App\Metas\Middleware;

use App\Metas\Constants\MetasConstants;
use App\Metas\Services\ConfiguracionMetasService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entrada a la lectura de metas: quien tiene `metas.listar`, o un asesor cuando la empresa activó la meta
 * consolidada. El detalle por asesor lo recorta `MetaService::listar`, no este middleware.
 */
class AccesoMetas
{
    public function __construct(protected ConfiguracionMetasService $configuracion) {}

    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $usuario = $peticion->user();

        if ($usuario?->tienePermiso(MetasConstants::MODULO, 'listar')) {
            return $siguiente($peticion);
        }

        $esAsesor = $usuario?->rol?->nombre === MetasConstants::ROL_ASESOR;

        if ($esAsesor && $this->configuracion->parametros((int) $usuario->empresa_id)->metaConsolidadaVisibleAsesor) {
            return $siguiente($peticion);
        }

        abort(403, 'No tienes permiso para realizar esta acción.');
    }
}
