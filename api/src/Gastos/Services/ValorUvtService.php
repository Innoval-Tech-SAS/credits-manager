<?php

namespace App\Gastos\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Services\EmisorAuditoria;
use App\Gastos\Models\ValorUvt;
use App\Gastos\Repositories\ValorUvtRepository;
use Illuminate\Database\Eloquent\Collection;

class ValorUvtService
{
    public function __construct(
        protected ValorUvtRepository $valoresUvt,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function listar(): Collection
    {
        return $this->valoresUvt->todos();
    }

    public function guardar(int $anio, float $valor): ValorUvt
    {
        $uvt = $this->valoresUvt->guardar($anio, $valor);

        $this->auditoria->registrarGuardado(AuditoriaConstants::MODULO_GASTOS, "Valor UVT del año {$anio} guardado", $uvt);

        return $uvt;
    }
}
