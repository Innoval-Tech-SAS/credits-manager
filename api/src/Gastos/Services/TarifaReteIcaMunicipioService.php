<?php

namespace App\Gastos\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Services\EmisorAuditoria;
use App\Gastos\Models\TarifaReteIcaMunicipio;
use App\Gastos\Repositories\TarifaReteIcaMunicipioRepository;
use App\Geografia\Models\Municipio;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TarifaReteIcaMunicipioService
{
    public function __construct(
        protected TarifaReteIcaMunicipioRepository $tarifas,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function listar(): Collection
    {
        return $this->tarifas->todas();
    }

    /**
     * Mismo patrón de vigencia de {@see ParametrizacionTipoGastoService::crear()}: cierra la
     * vigente antes de crear la nueva, nunca la sobrescribe. La verificación y el cierre se
     * hacen dentro de la misma transacción, bajo `lockForUpdate()` sobre el `Municipio`, para
     * evitar vigencias abiertas duplicadas creadas por requests concurrentes.
     */
    public function crear(array $datos): TarifaReteIcaMunicipio
    {
        return DB::transaction(function () use ($datos) {
            Municipio::whereKey($datos['municipio_id'])->lockForUpdate()->firstOrFail();

            if ($this->tarifas->existeEnRangoCerrado($datos['municipio_id'], $datos['actividad_economica_id'], $datos['fecha_desde'])) {
                throw ValidationException::withMessages([
                    'fecha_desde' => __('validation.custom.tarifa_reteica.solapa_rango_cerrado'),
                ]);
            }

            $abierta = $this->tarifas->abiertaPara($datos['municipio_id'], $datos['actividad_economica_id']);

            if ($abierta) {
                $anteriores = $this->auditoria->instantanea($abierta);

                $this->tarifas->actualizar($abierta, [
                    'fecha_hasta' => Carbon::parse($datos['fecha_desde'])->subDay()->toDateString(),
                ]);

                $this->auditoria->registrarModificacion(
                    AuditoriaConstants::MODULO_GASTOS,
                    "Tarifa ReteICA #{$abierta->id} del municipio #{$abierta->municipio_id} cerrada por una nueva vigencia",
                    $abierta,
                    $anteriores,
                );
            }

            $tarifa = $this->tarifas->crear($datos);

            $this->auditoria->registrarCreacion(
                AuditoriaConstants::MODULO_GASTOS,
                "Tarifa ReteICA #{$tarifa->id} del municipio #{$tarifa->municipio_id} vigente desde {$datos['fecha_desde']}",
                $tarifa,
            );

            return $tarifa;
        });
    }
}
