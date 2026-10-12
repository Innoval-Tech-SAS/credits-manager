<?php

namespace App\Causacion\Repositories;

use App\Causacion\Models\TarifaReteIcaMunicipio;
use App\Core\Base\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

class TarifaReteIcaMunicipioRepository extends BaseRepository
{
    public function __construct(TarifaReteIcaMunicipio $tarifa)
    {
        parent::__construct($tarifa);
    }

    public function todas(): Collection
    {
        return $this->consulta()->with(['municipio', 'actividadEconomica'])->orderByDesc('fecha_desde')->get();
    }

    public function vigenteParaFecha(int $municipioId, int $actividadEconomicaId, string $fecha): ?TarifaReteIcaMunicipio
    {
        return $this->consulta()
            ->where('municipio_id', $municipioId)
            ->where('actividad_economica_id', $actividadEconomicaId)
            ->where('fecha_desde', '<=', $fecha)
            ->where(fn ($query) => $query->whereNull('fecha_hasta')->orWhere('fecha_hasta', '>=', $fecha))
            ->first();
    }

    /** La tarifa vigente sin fecha de cierre para este municipio+actividad, si existe (la que hay que cerrar al crear una nueva versión). */
    public function abiertaPara(int $municipioId, int $actividadEconomicaId): ?TarifaReteIcaMunicipio
    {
        return $this->consulta()
            ->where('municipio_id', $municipioId)
            ->where('actividad_economica_id', $actividadEconomicaId)
            ->whereNull('fecha_hasta')
            ->first();
    }

    public function existeEnRangoCerrado(int $municipioId, int $actividadEconomicaId, string $fechaDesde): bool
    {
        return $this->consulta()
            ->where('municipio_id', $municipioId)
            ->where('actividad_economica_id', $actividadEconomicaId)
            ->whereNotNull('fecha_hasta')
            ->where('fecha_desde', '<=', $fechaDesde)
            ->where('fecha_hasta', '>=', $fechaDesde)
            ->exists();
    }
}
