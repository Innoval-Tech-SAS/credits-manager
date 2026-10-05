<?php

namespace App\Importacion\Commands;

use App\Importacion\Constants\ImportacionConstants;
use App\Importacion\Enums\EstadoImportacion;
use App\Importacion\Models\Importacion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** Borra los CSV de importaciones terminadas. El registro se conserva para la bitácora y el historial. */
class LimpiarImportacionesCommand extends Command
{
    protected $signature = 'importaciones:limpiar {--dias=7 : Antigüedad mínima, en días, de las importaciones a limpiar}';

    protected $description = 'Borra del volumen los CSV de importaciones terminadas hace más de N días (el registro se conserva)';

    public function handle(): int
    {
        $dias = max(1, (int) $this->option('dias'));
        $disco = Storage::disk(ImportacionConstants::DISCO);
        $limpiadas = 0;

        Importacion::query()
            ->whereIn('estado', [EstadoImportacion::Completada->value, EstadoImportacion::Fallida->value, EstadoImportacion::Cancelada->value])
            ->whereNotNull('archivo_ruta')
            ->where('creado_en', '<', now()->subDays($dias))
            ->chunkById(200, function ($importaciones) use ($disco, &$limpiadas) {
                foreach ($importaciones as $importacion) {
                    $disco->delete($importacion->archivo_ruta);
                    $importacion->forceFill(['archivo_ruta' => null])->save();
                    $limpiadas++;
                }
            });

        $this->info("Importaciones limpiadas: {$limpiadas}");

        return self::SUCCESS;
    }
}
