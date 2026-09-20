<?php

namespace App\Contabilidad\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Services\EmisorAuditoria;
use App\Contabilidad\Models\PlanCuenta;
use App\Contabilidad\Repositories\PlanCuentaRepository;
use Illuminate\Database\Eloquent\Collection;

class PlanCuentaService
{
    public function __construct(
        protected PlanCuentaRepository $cuentas,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function listar(?string $busqueda = null): Collection
    {
        return $this->cuentas->todasOrdenadas($busqueda);
    }

    public function crear(array $datos): PlanCuenta
    {
        $cuenta = $this->cuentas->crear($datos);

        $this->auditoria->registrarCreacion(AuditoriaConstants::MODULO_CONTABILIDAD, "Cuenta {$cuenta->codigo} {$cuenta->nombre} creada", $cuenta);

        return $cuenta;
    }

    public function editar(PlanCuenta $cuenta, array $datos): PlanCuenta
    {
        $anteriores = $this->auditoria->instantanea($cuenta);
        $cuenta = $this->cuentas->actualizar($cuenta, $datos);

        $this->auditoria->registrarModificacion(AuditoriaConstants::MODULO_CONTABILIDAD, "Cuenta {$cuenta->codigo} modificada", $cuenta, $anteriores);

        return $cuenta;
    }
}
