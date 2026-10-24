<?php

namespace App\Causacion\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Services\EmisorAuditoria;
use App\Causacion\Models\ParametrizacionTipoGasto;
use App\Causacion\Models\TipoGasto;
use App\Causacion\Repositories\ParametrizacionTipoGastoRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ParametrizacionTipoGastoService
{
    public function __construct(
        protected ParametrizacionTipoGastoRepository $parametrizaciones,
        protected EmisorAuditoria $auditoria,
    ) {}

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
                $anteriores = $this->auditoria->instantanea($abierta);

                $this->parametrizaciones->actualizar($abierta, [
                    'fecha_hasta' => Carbon::parse($datos['fecha_desde'])->subDay()->toDateString(),
                ]);

                $this->auditoria->registrarModificacion(
                    AuditoriaConstants::MODULO_GASTOS,
                    "Parametrización #{$abierta->id} del tipo de gasto #{$abierta->tipo_gasto_id} cerrada por una nueva vigencia",
                    $abierta,
                    $anteriores,
                );
            }

            $parametrizacion = $this->parametrizaciones->crear($datos);

            $this->auditoria->registrarCreacion(
                AuditoriaConstants::MODULO_GASTOS,
                "Parametrización #{$parametrizacion->id} del tipo de gasto #{$parametrizacion->tipo_gasto_id} vigente desde {$datos['fecha_desde']}",
                $parametrizacion,
            );

            return $parametrizacion;
        });
    }

    /**
     * Corrige la configuración contable de una versión en su lugar. Los gastos ya causados no cambian: su asiento y
     * las retenciones aplicadas quedaron guardados al causarse; solo los gastos que se causen desde ahora usan lo nuevo.
     * Una retención que se apaga deja sus campos en nulo para no conservar una cuenta o tarifa que ya no aplica.
     */
    public function editar(ParametrizacionTipoGasto $parametrizacion, array $datos): ParametrizacionTipoGasto
    {
        $retenciones = [
            'aplica_retefuente' => ['concepto_dian', 'base_minima_uvt', 'tarifa_retefuente_persona_natural', 'tarifa_retefuente_persona_juridica', 'cuenta_retefuente_id'],
            'aplica_reteica' => ['cuenta_reteica_id'],
            'aplica_reteiva' => ['tarifa_reteiva', 'cuenta_reteiva_id'],
        ];

        foreach ($retenciones as $bandera => $campos) {
            if (array_key_exists($bandera, $datos) && ! $datos[$bandera]) {
                $datos = [...$datos, ...array_fill_keys($campos, null)];
            }
        }

        return DB::transaction(function () use ($parametrizacion, $datos) {
            $anteriores = $this->auditoria->instantanea($parametrizacion);

            $parametrizacion = $this->parametrizaciones->actualizar($parametrizacion, $datos);

            $this->auditoria->registrarModificacion(
                AuditoriaConstants::MODULO_GASTOS,
                "Parametrización #{$parametrizacion->id} del tipo de gasto #{$parametrizacion->tipo_gasto_id} editada",
                $parametrizacion,
                $anteriores,
            );

            return $parametrizacion;
        });
    }
}
