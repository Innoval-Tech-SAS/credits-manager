<?php

namespace App\Contabilidad\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Services\EmisorAuditoria;
use App\Contabilidad\Enums\EventoContable;
use App\Contabilidad\Models\ReglaContabilizacion;
use App\Contabilidad\Repositories\ReglaContabilizacionRepository;
use Illuminate\Support\Collection;

class ReglaContabilizacionService
{
    public function __construct(
        protected ReglaContabilizacionRepository $reglas,
        protected EmisorAuditoria $auditoria,
    ) {}

    /** @return Collection<string, ReglaContabilizacion> indexada por evento */
    public function listar(): Collection
    {
        return $this->reglas->todasPorEvento();
    }

    public function guardar(EventoContable $evento, int $cuentaDebitoId, int $cuentaCreditoId, int $tipoComprobanteId): ReglaContabilizacion
    {
        $regla = $this->reglas->guardar($evento, $cuentaDebitoId, $cuentaCreditoId, $tipoComprobanteId);

        $this->auditoria->registrarGuardado(AuditoriaConstants::MODULO_CONTABILIDAD, "Regla de contabilización del evento {$evento->value} guardada", $regla);

        return $regla;
    }
}
