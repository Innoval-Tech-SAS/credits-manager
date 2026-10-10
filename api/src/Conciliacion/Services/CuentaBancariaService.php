<?php

namespace App\Conciliacion\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Services\EmisorAuditoria;
use App\Conciliacion\Models\CuentaBancaria;
use App\Conciliacion\Repositories\CuentaBancariaRepository;
use Illuminate\Database\Eloquent\Collection;

class CuentaBancariaService
{
    public function __construct(
        protected CuentaBancariaRepository $cuentasBancarias,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function listar(): Collection
    {
        return $this->cuentasBancarias->todas();
    }

    public function crear(array $datos): CuentaBancaria
    {
        $cuentaBancaria = $this->cuentasBancarias->crear($datos);

        $this->auditoria->registrarCreacion(
            AuditoriaConstants::MODULO_CONCILIACION,
            "Cuenta bancaria {$cuentaBancaria->numero_cuenta} ({$cuentaBancaria->banco->etiqueta()}) creada",
            $cuentaBancaria,
        );

        return $cuentaBancaria;
    }

    public function editar(CuentaBancaria $cuentaBancaria, array $datos): CuentaBancaria
    {
        $anteriores = $this->auditoria->instantanea($cuentaBancaria);
        $cuentaBancaria = $this->cuentasBancarias->actualizar($cuentaBancaria, $datos);

        $this->auditoria->registrarModificacion(
            AuditoriaConstants::MODULO_CONCILIACION,
            "Cuenta bancaria {$cuentaBancaria->numero_cuenta} modificada",
            $cuentaBancaria,
            $anteriores,
        );

        return $cuentaBancaria;
    }
}
