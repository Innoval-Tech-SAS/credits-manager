<?php

namespace App\Gastos\Services;

use App\Gastos\Models\ParametrizacionTipoGasto;
use App\Gastos\Models\TipoGasto;
use App\Gastos\Repositories\ParametrizacionTipoGastoRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ParametrizacionTipoGastoService
{
    public function __construct(protected ParametrizacionTipoGastoRepository $parametrizaciones) {}

    public function listarPorTipoGasto(int $tipoGastoId): Collection
    {
        return $this->parametrizaciones->porTipoGasto($tipoGastoId);
    }

    /**
     * Nunca sobrescribe una ficha ya guardada: si hay una vigencia abierta (sin
     * `fecha_hasta`) para el mismo tipo de gasto, solo le cierra la fecha (un día
     * antes de la nueva) y crea la ficha nueva como fila aparte.
     *
     * La verificación de solapamiento y el cierre de la vigencia abierta se hacen
     * dentro de la misma transacción, bajo `lockForUpdate()` sobre el `TipoGasto`,
     * para evitar que dos requests concurrentes creen dos vigencias abiertas
     * duplicadas para el mismo tipo de gasto.
     */
    public function crear(array $datos): ParametrizacionTipoGasto
    {
        return DB::transaction(function () use ($datos) {
            TipoGasto::whereKey($datos['tipo_gasto_id'])->lockForUpdate()->firstOrFail();

            if ($this->parametrizaciones->existeEnRangoCerrado($datos['tipo_gasto_id'], $datos['fecha_desde'])) {
                throw ValidationException::withMessages([
                    'fecha_desde' => __('validation.custom.parametrizacion.solapa_rango_cerrado'),
                ]);
            }

            $abierta = $this->parametrizaciones->abiertaParaTipoGasto($datos['tipo_gasto_id']);

            if ($abierta) {
                $this->parametrizaciones->actualizar($abierta, [
                    'fecha_hasta' => Carbon::parse($datos['fecha_desde'])->subDay()->toDateString(),
                ]);
            }

            return $this->parametrizaciones->crear($datos);
        });
    }
}
