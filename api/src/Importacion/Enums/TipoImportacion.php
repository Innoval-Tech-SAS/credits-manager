<?php

namespace App\Importacion\Enums;

enum TipoImportacion: string
{
    case Clientes = 'clientes';
    case Creditos = 'creditos';
    case PlanCuotas = 'plan_cuotas';
    case Pagos = 'pagos';
    case SaldosIniciales = 'saldos_iniciales';
    case PlanCuentas = 'plan_cuentas';
    case Terceros = 'terceros';
    case Gastos = 'gastos';
}
