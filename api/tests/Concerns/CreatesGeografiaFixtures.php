<?php

namespace Tests\Concerns;

use App\Geografia\Models\Estado;
use App\Geografia\Models\Municipio;
use App\Geografia\Models\Pais;

/** Fixtures del catálogo geográfico global: cada nivel crea su padre si no se le indica uno. */
trait CreatesGeografiaFixtures
{
    protected function crearPais(array $overrides = []): Pais
    {
        return Pais::firstOrCreate(
            ['codigo' => $overrides['codigo'] ?? 'CO'],
            array_merge(['nombre' => 'Colombia'], $overrides),
        );
    }

    protected function crearEstado(array $overrides = []): Estado
    {
        static $contador = 0;
        $contador++;

        return Estado::create(array_merge([
            'pais_id' => $this->crearPais()->id,
            'codigo' => sprintf('%02d', $contador),
            'nombre' => "Estado de prueba {$contador}",
        ], $overrides));
    }

    protected function crearMunicipio(array $overrides = []): Municipio
    {
        static $contador = 0;
        $contador++;

        return Municipio::create(array_merge([
            'estado_id' => $this->crearEstado()->id,
            'codigo' => sprintf('%05d', $contador),
            'nombre' => "Municipio de prueba {$contador}",
        ], $overrides));
    }
}
