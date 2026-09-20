<?php

namespace App\Contabilidad\Services;

use App\Contabilidad\Enums\EventoContable;
use App\Contabilidad\Models\ReglaContabilizacion;
use App\Contabilidad\Repositories\ReglaContabilizacionRepository;
use Illuminate\Support\Collection;

class ReglaContabilizacionService
{
    public function __construct(protected ReglaContabilizacionRepository $reglas) {}

    /** @return Collection<string, ReglaContabilizacion> indexada por evento */
    public function listar(): Collection
    {
        return $this->reglas->todasPorEvento();
    }

    public function guardar(EventoContable $evento, int $cuentaDebitoId, int $cuentaCreditoId, int $tipoComprobanteId): ReglaContabilizacion
    {
        return $this->reglas->guardar($evento, $cuentaDebitoId, $cuentaCreditoId, $tipoComprobanteId);
    }
}
