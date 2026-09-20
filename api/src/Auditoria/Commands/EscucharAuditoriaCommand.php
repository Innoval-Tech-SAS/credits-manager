<?php

namespace App\Auditoria\Commands;

use App\Auditoria\Services\EscuchaAuditoria;
use Illuminate\Console\Command;

class EscucharAuditoriaCommand extends Command
{
    protected $signature = 'auditoria:escuchar {--consumidor= : Nombre del consumidor del grupo (por defecto, el hostname)}';

    protected $description = 'Listener de larga duración: lee el stream de auditoría de Redis e inserta cada evento en la bitácora';

    public function handle(EscuchaAuditoria $escucha): int
    {
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            // La imagen php:fpm declara STOPSIGNAL SIGQUIT: sin manejarlo, siendo PID 1, `stop` acabaría en SIGKILL.
            foreach ([SIGTERM, SIGINT, SIGQUIT] as $senal) {
                pcntl_signal($senal, fn () => $escucha->solicitarParada());
            }
        }

        $escucha->iniciar($this->option('consumidor') ?: (gethostname() ?: 'escucha-'.getmypid()));

        return self::SUCCESS;
    }
}
