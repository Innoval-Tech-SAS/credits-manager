<?php

namespace Database\Seeders;

use App\Monitoreo\Constants\TareaProgramadaConstants;
use App\Monitoreo\Models\TareaProgramada;
use Illuminate\Database\Seeder;

/** Catálogo fijo de tareas programadas: la fuente de verdad es TareaProgramadaConstants::CLAVES. */
class TareaProgramadaSeeder extends Seeder
{
    public function run(): void
    {
        foreach (TareaProgramadaConstants::CLAVES as $clave) {
            if (TareaProgramada::query()->where('clave', $clave)->exists()) {
                continue;
            }

            // `clave` no está en Fillable (es inmutable): forceCreate() la asigna sin pasar por ese guardado.
            TareaProgramada::query()->forceCreate([
                'clave' => $clave,
                'hora' => TareaProgramadaConstants::HORAS_POR_DEFECTO[$clave],
                'activa' => true,
            ]);
        }
    }
}
