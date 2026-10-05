<?php

namespace App\Importacion\Procesadores;

use App\Importacion\Enums\TipoImportacion;

class ProcesadorFilaFactory
{
    public function para(TipoImportacion $tipo): ProcesadorFila
    {
        return app(match ($tipo) {
            TipoImportacion::Clientes => ProcesadorClientes::class,
            TipoImportacion::Creditos => ProcesadorCreditos::class,
            TipoImportacion::PlanCuotas => ProcesadorPlanCuotas::class,
            TipoImportacion::Pagos => ProcesadorPagos::class,
            TipoImportacion::SaldosIniciales => ProcesadorSaldosIniciales::class,
            TipoImportacion::PlanCuentas => ProcesadorPlanCuentas::class,
            TipoImportacion::Terceros => ProcesadorTerceros::class,
        });
    }
}
