<?php

namespace App\Metas\Services;

use App\Metas\Repositories\MetaRepository;
use Carbon\CarbonImmutable;

/** Lo que el comando diario hace por cada empresa: cerrar lo vencido y generar las alertas. Corre sin sesión. */
class EvaluacionMetasService
{
    public function __construct(
        protected CierreMetasService $cierres,
        protected CumplimientoMetasService $cumplimiento,
        protected ConfiguracionMetasService $configuracion,
        protected AlertaMetasService $alertas,
        protected MetaRepository $metas,
    ) {}

    /** @return int número de metas cerradas en esta corrida */
    public function evaluarEmpresa(int $empresaId, CarbonImmutable $ahora): int
    {
        $cerradas = $this->cierres->cerrarVencidas($empresaId, $ahora);

        $resumen = $this->cumplimiento->resumen(
            $empresaId,
            $this->metas->deEmpresa($empresaId),
            $ahora,
            $this->configuracion->parametros($empresaId),
        );

        $this->alertas->generar($empresaId, $resumen, $ahora);

        return $cerradas;
    }
}
