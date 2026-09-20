<?php

namespace App\Contabilidad\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Services\EmisorAuditoria;
use App\Contabilidad\Models\CentroCosto;
use App\Contabilidad\Repositories\CentroCostoRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class CentroCostoService
{
    public function __construct(
        protected CentroCostoRepository $centros,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function paginar(?string $busqueda, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->centros->paginar($busqueda, max(1, min(100, $porPagina)), max(1, $pagina));
    }

    public function crear(array $datos): CentroCosto
    {
        $centro = $this->centros->crear($datos);

        $this->auditoria->registrarCreacion(AuditoriaConstants::MODULO_CONTABILIDAD, "Centro de costo {$centro->codigo} {$centro->nombre} creado", $centro);

        return $centro;
    }

    public function editar(CentroCosto $centro, array $datos): CentroCosto
    {
        $anteriores = $this->auditoria->instantanea($centro);
        $centro = $this->centros->actualizar($centro, $datos);

        $this->auditoria->registrarModificacion(AuditoriaConstants::MODULO_CONTABILIDAD, "Centro de costo {$centro->codigo} modificado", $centro, $anteriores);

        return $centro;
    }
}
