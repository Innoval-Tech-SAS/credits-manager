<?php

namespace App\Contabilidad\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Services\EmisorAuditoria;
use App\Contabilidad\Models\TipoComprobante;
use App\Contabilidad\Repositories\TipoComprobanteRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class TipoComprobanteService
{
    public function __construct(
        protected TipoComprobanteRepository $tipos,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function paginar(?string $busqueda, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->tipos->paginar($busqueda, max(1, min(100, $porPagina)), max(1, $pagina));
    }

    public function crear(array $datos): TipoComprobante
    {
        $tipo = $this->tipos->crear($datos);

        $this->auditoria->registrarCreacion(AuditoriaConstants::MODULO_CONTABILIDAD, "Tipo de comprobante {$tipo->codigo} {$tipo->nombre} creado", $tipo);

        return $tipo;
    }
}
