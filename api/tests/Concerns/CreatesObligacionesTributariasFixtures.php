<?php

namespace Tests\Concerns;

use App\ObligacionesTributarias\Enums\PeriodicidadObligacion;
use App\ObligacionesTributarias\Models\ObligacionTributaria;
use App\ObligacionesTributarias\Models\TipoObligacionTributaria;

/** Fixtures del calendario tributario — evita repetir el armado de tipo + obligación en cada test. */
trait CreatesObligacionesTributariasFixtures
{
    protected function crearTipoObligacion(array $overrides = []): TipoObligacionTributaria
    {
        static $contador = 0;
        $contador++;

        return TipoObligacionTributaria::create(array_merge([
            'nombre' => "Obligación de prueba {$contador}",
            'periodicidad' => PeriodicidadObligacion::Mensual,
            'dias_alerta_previa' => 5,
            'activo' => true,
        ], $overrides));
    }

    protected function crearObligacion(TipoObligacionTributaria $tipo, array $overrides = []): ObligacionTributaria
    {
        static $contador = 0;
        $contador++;

        return ObligacionTributaria::create(array_merge([
            'tipo_obligacion_id' => $tipo->id,
            'periodo' => "2026-0{$contador}",
            'fecha_limite' => '2026-10-20',
            'estado' => 'pendiente',
        ], $overrides));
    }
}
