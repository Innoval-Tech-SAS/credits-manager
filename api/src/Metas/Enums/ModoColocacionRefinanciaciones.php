<?php

namespace App\Metas\Enums;

enum ModoColocacionRefinanciaciones: string
{
    case Excluir = 'excluir';
    case SoloDineroNuevo = 'solo_dinero_nuevo';
    case Total = 'total';
}
