<?php

namespace App\Correo\Services;

use App\Correo\Enums\CifradoCorreo;
use App\Correo\Models\ConfiguracionCorreo;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Mail;

/** Construye el mailer desde la fila de la empresa. Sin estado: cada envío parte de la configuración vigente. */
class FabricaMailerCobranza
{
    public function paraConfiguracion(ConfiguracionCorreo $configuracion): Mailer
    {
        return Mail::build($this->transporte($configuracion));
    }

    /** @return array<string, mixed> */
    public function transporte(ConfiguracionCorreo $configuracion): array
    {
        return [
            'transport' => 'smtp',
            'name' => 'cobranza',
            'scheme' => $configuracion->cifrado === CifradoCorreo::Ssl ? 'smtps' : 'smtp',
            'host' => $configuracion->host,
            'port' => $configuracion->puerto,
            'username' => $configuracion->usuario,
            'password' => $configuracion->clave,
            'timeout' => 15,
        ];
    }
}
