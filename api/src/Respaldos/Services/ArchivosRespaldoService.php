<?php

namespace App\Respaldos\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

/** Empaqueta los directorios de archivos (adjuntos, logos, exportaciones, importaciones) en un tar.gz. */
class ArchivosRespaldoService
{
    public function archivar(string $destino): void
    {
        $directorios = config('respaldos.directorios');
        $argumentos = ['tar', '-czf', $destino];

        foreach ($directorios as $directorio) {
            if (! is_dir($directorio)) {
                throw new RuntimeException('No existe el directorio que se debe respaldar.');
            }

            array_push($argumentos, '-C', dirname($directorio), basename($directorio));
        }

        $proceso = new Process($argumentos);
        $proceso->setTimeout(null);
        $proceso->run();

        if (! $proceso->isSuccessful()) {
            throw new RuntimeException('No se pudieron empaquetar los archivos: '.mb_substr(trim($proceso->getErrorOutput()) ?: 'sin detalle', 0, 300));
        }
    }
}
