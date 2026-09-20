<?php

namespace App\Gastos\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Services\EmisorAuditoria;
use App\Gastos\Models\Municipio;
use App\Gastos\Repositories\MunicipioRepository;
use Illuminate\Database\Eloquent\Collection;

class MunicipioService
{
    public function __construct(
        protected MunicipioRepository $municipios,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function listar(): Collection
    {
        return $this->municipios->todos();
    }

    public function crear(array $datos): Municipio
    {
        $municipio = $this->municipios->crear($datos);

        $this->auditoria->registrarCreacion(AuditoriaConstants::MODULO_GASTOS, "Municipio {$municipio->codigo} {$municipio->nombre} creado", $municipio);

        return $municipio;
    }

    public function editar(Municipio $municipio, array $datos): Municipio
    {
        $anteriores = $this->auditoria->instantanea($municipio);
        $municipio = $this->municipios->actualizar($municipio, $datos);

        $this->auditoria->registrarModificacion(AuditoriaConstants::MODULO_GASTOS, "Municipio {$municipio->codigo} modificado", $municipio, $anteriores);

        return $municipio;
    }
}
