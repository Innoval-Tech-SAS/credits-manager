<?php

namespace App\Contabilidad\Repositories;

use App\Contabilidad\Enums\EventoContable;
use App\Contabilidad\Models\ReglaContabilizacion;
use App\Core\Base\BaseRepository;
use Illuminate\Support\Collection;

class ReglaContabilizacionRepository extends BaseRepository
{
    public function __construct(ReglaContabilizacion $reglaContabilizacion)
    {
        parent::__construct($reglaContabilizacion);
    }

    public function activaParaEvento(EventoContable $evento): ?ReglaContabilizacion
    {
        return $this->consulta()->where('evento', $evento)->where('activo', true)->first();
    }

    /** @return Collection<string, ReglaContabilizacion> indexada por valor del evento */
    public function todasPorEvento(): Collection
    {
        return $this->consulta()->get()->keyBy(fn (ReglaContabilizacion $regla) => $regla->evento->value);
    }

    public function guardar(EventoContable $evento, int $cuentaDebitoId, int $cuentaCreditoId, int $tipoComprobanteId): ReglaContabilizacion
    {
        return $this->consulta()->updateOrCreate(
            ['evento' => $evento],
            [
                'cuenta_debito_id' => $cuentaDebitoId,
                'cuenta_credito_id' => $cuentaCreditoId,
                'tipo_comprobante_id' => $tipoComprobanteId,
                'activo' => true,
            ],
        );
    }
}
