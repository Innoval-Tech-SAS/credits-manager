<?php

namespace App\Gastos\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Services\EmisorAuditoria;
use App\Gastos\Models\ActividadEconomica;
use App\Gastos\Repositories\ActividadEconomicaRepository;
use Illuminate\Database\Eloquent\Collection;

class ActividadEconomicaService
{
    public function __construct(
        protected ActividadEconomicaRepository $actividades,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function listar(): Collection
    {
        return $this->actividades->todos();
    }

    public function crear(array $datos): ActividadEconomica
    {
        $actividad = $this->actividades->crear($datos);

        $this->auditoria->registrarCreacion(AuditoriaConstants::MODULO_GASTOS, "Actividad económica {$actividad->codigo} {$actividad->nombre} creado", $actividad);

        return $actividad;
    }

    public function editar(ActividadEconomica $actividad, array $datos): ActividadEconomica
    {
        $anteriores = $this->auditoria->instantanea($actividad);
        $actividad = $this->actividades->actualizar($actividad, $datos);

        $this->auditoria->registrarModificacion(AuditoriaConstants::MODULO_GASTOS, "Actividad económica {$actividad->codigo} modificado", $actividad, $anteriores);

        return $actividad;
    }
}
