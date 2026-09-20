<?php

namespace App\Exportacion\Commands;

use App\Exportacion\Constants\ExportacionConstants;
use App\Exportacion\Repositories\ExportacionRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class LimpiarExportacionesCommand extends Command
{
    protected $signature = 'exportaciones:limpiar {--dias=7 : Antigüedad mínima, en días, de las exportaciones a limpiar}';

    protected $description = 'Borra del volumen los archivos de exportaciones vencidas y archiva su registro (soft delete)';

    public function handle(ExportacionRepository $exportaciones): int
    {
        $dias = max(1, (int) $this->option('dias'));
        $disco = Storage::disk(ExportacionConstants::DISCO);
        $limpiadas = 0;

        foreach ($exportaciones->vencidas(now()->subDays($dias)) as $exportacion) {
            if ($exportacion->ruta_archivo) {
                $disco->delete($exportacion->ruta_archivo);
            }

            $exportaciones->eliminar($exportacion);
            $limpiadas++;
        }

        $this->info("Exportaciones limpiadas: {$limpiadas}");

        return self::SUCCESS;
    }
}
