<?php

namespace App\Monitoreo\Enums;

enum EstadoServicio: string
{
    case Activo = 'activo';
    case Ocupado = 'ocupado';
    case Caido = 'caido';
}
