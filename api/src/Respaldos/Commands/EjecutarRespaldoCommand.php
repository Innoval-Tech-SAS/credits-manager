<?php

namespace App\Respaldos\Commands;

use App\Respaldos\Services\RespaldoService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Corre sin sesión (scheduler o consola): respalda la instancia completa, todas las empresas a la vez.
 * No pasa por los repositorios de negocio ni por EmpresaScope; no expone nada por la API.
 */
class EjecutarRespaldoCommand extends Command
{
    protected $signature = 'respaldos:ejecutar {--manual : Respaldo antes de una operación crítica (migración, cierre de período)}';

    protected $description = 'Crea el respaldo de la base de datos y de los archivos, y aplica la retención';

    public function handle(RespaldoService $respaldos): int
    {
        $manual = (bool) $this->option('manual');

        try {
            $creadas = $respaldos->ejecutar(CarbonImmutable::now(config('app.timezone')), $manual);
        } catch (Throwable $e) {
            $this->error('El respaldo falló: '.$e->getMessage());

            return self::FAILURE;
        }

        foreach ($creadas as $clase => $ruta) {
            $this->info("Respaldo {$clase} creado en {$ruta}");
        }

        return self::SUCCESS;
    }
}
