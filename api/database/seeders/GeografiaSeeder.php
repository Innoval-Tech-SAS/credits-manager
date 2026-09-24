<?php

namespace Database\Seeders;

use App\Geografia\Models\Estado;
use App\Geografia\Models\Municipio;
use App\Geografia\Models\Pais;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Colombia completa según DIVIPOLA (DANE). Idempotente: se puede correr de nuevo para completar el catálogo
 * y no toca `activo`, que decide el superadmin. Catálogo global, no depende de ninguna empresa ni de sesión.
 */
class GeografiaSeeder extends Seeder
{
    public function run(): void
    {
        $colombia = Pais::updateOrCreate(['codigo' => 'CO'], ['nombre' => 'Colombia']);
        $ahora = Carbon::now();

        foreach (require __DIR__.'/data/divipola_colombia.php' as $datosEstado) {
            $estado = Estado::updateOrCreate(
                ['pais_id' => $colombia->id, 'codigo' => $datosEstado['codigo']],
                ['nombre' => $datosEstado['nombre']],
            );

            $filas = array_map(fn (array $municipio) => [
                'estado_id' => $estado->id,
                'codigo' => $municipio[0],
                'nombre' => $municipio[1],
                'es_capital' => $municipio[2],
                'creado_en' => $ahora,
                'ultima_actualizacion' => $ahora,
            ], $datosEstado['municipios']);

            Municipio::upsert($filas, ['estado_id', 'codigo'], ['nombre', 'es_capital', 'ultima_actualizacion']);
        }
    }
}
