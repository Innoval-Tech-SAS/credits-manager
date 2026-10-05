<?php

namespace App\Correo\Enums;

enum CifradoCorreo: string
{
    /** STARTTLS (p. ej. puerto 587). */
    case Tls = 'tls';

    /** TLS implícito (p. ej. puerto 465). */
    case Ssl = 'ssl';

    case Ninguno = 'ninguno';
}
