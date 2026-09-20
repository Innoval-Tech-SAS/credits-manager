<?php

namespace App\Contabilidad\Enums;

enum TipoCuenta: string
{
    case Activo = 'activo';
    case Pasivo = 'pasivo';
    case Patrimonio = 'patrimonio';
    case Ingreso = 'ingreso';
    case Gasto = 'gasto';
    case Costo = 'costo';
    case Orden = 'orden';
}
