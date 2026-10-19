<?php

namespace Database\Seeders;

use App\Respaldos\Models\ConfiguracionRespaldoExterno;
use Illuminate\Database\Seeder;

/** Fila única (id=1): el respaldo externo arranca desactivado hasta que se configure desde la UI. */
class ConfiguracionRespaldoExternoSeeder extends Seeder
{
    public function run(): void
    {
        if (ConfiguracionRespaldoExterno::query()->whereKey(1)->exists()) {
            return;
        }

        // `id` no está en Fillable: forceCreate() la asigna sin pasar por ese guardado, para fijar la fila en 1.
        ConfiguracionRespaldoExterno::query()->forceCreate([
            'id' => 1,
            'activa' => false,
            'uso_path_style' => false,
        ]);
    }
}
