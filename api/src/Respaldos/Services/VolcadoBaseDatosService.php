<?php

namespace App\Respaldos\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Vuelca la base de la conexión `mysql` a un SQL comprimido. La contraseña viaja solo en el
 * entorno del proceso (MYSQL_PWD): nunca en los argumentos, así no aparece en `ps`, ni en el
 * respaldo, ni en los mensajes de error.
 */
class VolcadoBaseDatosService
{
    public function volcar(string $destinoGz): void
    {
        $crudo = $destinoGz.'.sql';
        [$argumentos, $entorno] = $this->comando($crudo);

        try {
            $proceso = new Process($argumentos, env: $entorno);
            $proceso->setTimeout(null);
            $proceso->run();

            if (! $proceso->isSuccessful()) {
                throw new RuntimeException('El volcado de la base de datos falló: '.$this->resumen($proceso->getErrorOutput()));
            }

            $this->comprimir($crudo, $destinoGz);
        } finally {
            if (is_file($crudo)) {
                unlink($crudo);
            }
        }
    }

    /** Solo estructura: tablas, triggers y rutinas. Con `$conBorrado` la restauración sustituye lo existente. */
    public function volcarEsquema(string $destinoSql, bool $conBorrado = true): void
    {
        [$argumentos, $entorno] = $this->comando($destinoSql);
        $argumentos = array_values(array_filter($argumentos, fn (string $a) => ! str_starts_with($a, '--single-transaction') && ! str_starts_with($a, '--quick') && ! str_starts_with($a, '--ignore-table=')));
        array_splice($argumentos, 1, 0, ['--no-data']);

        if (! $conBorrado) {
            array_splice($argumentos, -1, 0, ['--skip-add-drop-table']);
        }

        $proceso = new Process($argumentos, env: $entorno);
        $proceso->setTimeout(null);
        $proceso->run();

        if (! $proceso->isSuccessful()) {
            throw new RuntimeException('El volcado de la estructura falló: '.$this->resumen($proceso->getErrorOutput()));
        }
    }

    /**
     * @return array{0: list<string>, 1: array<string, string>} argumentos y entorno del proceso
     */
    public function comando(string $crudo): array
    {
        $conexion = config('database.connections.mysql');
        $base = (string) $conexion['database'];

        $argumentos = [
            (string) config('respaldos.binario_volcado'),
            '--host='.$conexion['host'],
            '--port='.$conexion['port'],
            '--user='.$conexion['username'],
            '--single-transaction',
            '--quick',
            '--routines',
            '--events',
            '--triggers',
            '--no-tablespaces',
            '--default-character-set=utf8mb4',
            '--result-file='.$crudo,
        ];

        foreach (config('respaldos.tablas_excluidas') as $tabla) {
            $argumentos[] = "--ignore-table={$base}.{$tabla}";
        }

        $argumentos[] = $base;

        return [$argumentos, ['MYSQL_PWD' => (string) $conexion['password']]];
    }

    /** Escribe el SQL en gzip en streaming, sin cargarlo completo en memoria. */
    private function comprimir(string $crudo, string $destinoGz): void
    {
        $origen = fopen($crudo, 'rb');
        $destino = fopen('compress.zlib://'.$destinoGz, 'wb');

        if ($origen === false || $destino === false || stream_copy_to_stream($origen, $destino) === false) {
            throw new RuntimeException('No se pudo comprimir el volcado de la base de datos.');
        }

        fclose($origen);
        fclose($destino);
    }

    private function resumen(string $salida): string
    {
        return mb_substr(trim($salida) ?: 'sin detalle', 0, 300);
    }
}
