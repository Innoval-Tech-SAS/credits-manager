<?php

namespace App\Correo\Enums;

enum EstadoEnvioCorreo: string
{
    case EnCola = 'en_cola';
    case Enviado = 'enviado';
    case Fallido = 'fallido';
}
