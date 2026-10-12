<?php

namespace App\Importacion\Procesadores;

use App\Causacion\Services\TarifaReteIcaMunicipioService;
use App\Gastos\Models\ActividadEconomica;
use App\Geografia\Models\Municipio;
use App\Importacion\Data\ContextoEjecucion;
use App\Importacion\Data\FilaRechazada;
use App\Importacion\Lectura\ConvertidorValor;
use Illuminate\Validation\ValidationException;

/** Abre una vigencia nueva por municipio y actividad; el Service cierra la vigencia abierta anterior, si existía. */
final class ProcesadorTarifasReteica implements ProcesadorFila
{
    public function __construct(private readonly TarifaReteIcaMunicipioService $tarifas) {}

    public function precargar(array $valoresLote, ContextoEjecucion $ctx): void {}

    public function procesar(array $valores, ContextoEjecucion $ctx): void
    {
        $municipio = $this->municipio($valores['municipio']);
        $actividad = $this->actividadEconomica($ctx->empresaId, $valores['actividad_economica']);

        $fechaDesde = ConvertidorValor::fecha($valores['fecha_desde'], 'fecha_desde')
            ?? throw new FilaRechazada('La fecha desde es obligatoria.', 'fecha_desde');

        $tarifa = ConvertidorValor::decimal($valores['tarifa'], 'tarifa')
            ?? throw new FilaRechazada('La tarifa es obligatoria.', 'tarifa');

        if ((float) $tarifa < 0 || (float) $tarifa > 100) {
            throw new FilaRechazada('La tarifa debe estar entre 0 y 100.', 'tarifa');
        }

        if (! $ctx->escribir) {
            return;
        }

        try {
            $this->tarifas->crear([
                'municipio_id' => $municipio->id,
                'actividad_economica_id' => $actividad->id,
                'fecha_desde' => $fechaDesde,
                'tarifa' => $tarifa,
            ]);
        } catch (ValidationException $excepcion) {
            throw new FilaRechazada($excepcion->validator->errors()->first(), 'fecha_desde');
        }
    }

    private function municipio(string $codigo): Municipio
    {
        if ($codigo === '') {
            throw new FilaRechazada('El municipio es obligatorio.', 'municipio');
        }

        return Municipio::query()
            ->where('activo', true)
            ->where('codigo', $codigo)
            ->first()
            ?? throw new FilaRechazada("Municipio no encontrado: código DANE «{$codigo}».", 'municipio');
    }

    private function actividadEconomica(int $empresaId, string $codigo): ActividadEconomica
    {
        if ($codigo === '') {
            throw new FilaRechazada('La actividad económica es obligatoria.', 'actividad_economica');
        }

        return ActividadEconomica::query()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->where('codigo', $codigo)
            ->first()
            ?? throw new FilaRechazada("Actividad económica no encontrada o inactiva: código «{$codigo}».", 'actividad_economica');
    }
}
