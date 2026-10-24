<?php

namespace App\Respaldos\Services;

use App\Respaldos\Models\ConfiguracionRespaldoExterno;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/** Construye el disco S3 desde la fila de configuración. Sin estado: cada subida parte de la configuración vigente. */
class FabricaDiscoRespaldoExterno
{
    public function paraConfiguracion(ConfiguracionRespaldoExterno $configuracion): Filesystem
    {
        return Storage::build($this->opciones($configuracion));
    }

    /** @return array<string, mixed> */
    public function opciones(ConfiguracionRespaldoExterno $configuracion): array
    {
        return [
            'driver' => 's3',
            'key' => $configuracion->clave_acceso,
            'secret' => $configuracion->clave_secreta,
            'region' => $configuracion->region,
            'bucket' => $configuracion->bucket,
            'endpoint' => $configuracion->endpoint,
            'use_path_style_endpoint' => $configuracion->uso_path_style,
            'throw' => true,
        ];
    }
}
