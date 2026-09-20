<?php

namespace App\Gastos\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Services\EmisorAuditoria;
use App\Gastos\Models\TipoGasto;
use App\Gastos\Repositories\TipoGastoRepository;
use Illuminate\Database\Eloquent\Collection;

class TipoGastoService
{
    public function __construct(
        protected TipoGastoRepository $tiposGasto,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function listar(): Collection
    {
        return $this->tiposGasto->todos();
    }

    public function crear(array $datos): TipoGasto
    {
        $tipoGasto = $this->tiposGasto->crear($datos);

        $this->auditoria->registrarCreacion(AuditoriaConstants::MODULO_GASTOS, "Tipo de gasto {$tipoGasto->codigo} {$tipoGasto->nombre} creado", $tipoGasto);

        return $tipoGasto;
    }

    public function editar(TipoGasto $tipoGasto, array $datos): TipoGasto
    {
        $anteriores = $this->auditoria->instantanea($tipoGasto);
        $tipoGasto = $this->tiposGasto->actualizar($tipoGasto, $datos);

        $this->auditoria->registrarModificacion(AuditoriaConstants::MODULO_GASTOS, "Tipo de gasto {$tipoGasto->codigo} modificado", $tipoGasto, $anteriores);

        return $tipoGasto;
    }
}
