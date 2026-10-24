<?php

namespace App\Geografia\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Services\EmisorAuditoria;
use App\Geografia\Models\Municipio;
use App\Geografia\Repositories\MunicipioRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class MunicipioService
{
    public function __construct(
        protected MunicipioRepository $municipios,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function buscar(?int $estadoId, ?string $texto, bool $soloActivos): Collection
    {
        return $this->municipios->buscar($estadoId, $texto, $soloActivos);
    }

    public function listarPaginados(int $estadoId, bool $soloActivos, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->municipios->paginarPorEstado($estadoId, $soloActivos, $porPagina, $pagina);
    }

    public function crear(array $datos): Municipio
    {
        $municipio = $this->municipios->crear($datos);

        $this->auditoria->registrarCreacion(AuditoriaConstants::MODULO_GEOGRAFIA, "Municipio {$municipio->codigo} {$municipio->nombre} creado", $municipio);

        return $municipio->load('estado:id,pais_id,nombre');
    }

    public function editar(Municipio $municipio, array $datos): Municipio
    {
        $anteriores = $this->auditoria->instantanea($municipio);
        $municipio = $this->municipios->actualizar($municipio, $datos);

        $this->auditoria->registrarModificacion(AuditoriaConstants::MODULO_GEOGRAFIA, "Municipio {$municipio->codigo} modificado", $municipio, $anteriores);

        return $municipio->load('estado:id,pais_id,nombre');
    }
}
