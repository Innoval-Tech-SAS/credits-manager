<?php

namespace App\Correo\Enums;

enum DriverCorreo: string
{
    case Smtp = 'smtp';

    /** Proveedor de prueba: escribe el mensaje en el log de la aplicación y no sale a internet. */
    case Registro = 'registro';
}
