<?php

namespace App\Causacion\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Services\EmisorAuditoria;
use App\Causacion\Models\TipoGasto;
use App\Causacion\Repositories\TipoGastoRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class TipoGastoService
{
    public function __construct(
        protected TipoGastoRepository $tiposGasto,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function listar(): Collection
    {
        return $this->tiposGasto->todos();
    }

    public function crear(array $datos): TipoGasto
    {
        $tipoGasto = $this->tiposGasto->crear($datos);

        $this->auditoria->registrarCreacion(AuditoriaConstants::MODULO_GASTOS, "Tipo de gasto {$tipoGasto->codigo} {$tipoGasto->nombre} creado", $tipoGasto);

        return $tipoGasto;
    }

    /** Para que el frontend deshabilite campos del catálogo con una pista, en vez de que el usuario choque con un 422. */
    public function tieneGastosAsociados(TipoGasto $tipoGasto): bool
    {
        return $this->tiposGasto->tieneGastosAsociados($tipoGasto->id);
    }

    public function editar(TipoGasto $tipoGasto, array $datos): TipoGasto
    {
        $anteriores = $this->auditoria->instantanea($tipoGasto);
        $tipoGasto = $this->tiposGasto->actualizar($tipoGasto, $datos);

        $this->auditoria->registrarModificacion(AuditoriaConstants::MODULO_GASTOS, "Tipo de gasto {$tipoGasto->codigo} modificado", $tipoGasto, $anteriores);

        return $tipoGasto;
    }

    public function eliminar(TipoGasto $tipoGasto): bool
    {
        $this->exigirSinGastos($tipoGasto);
        $this->exigirSinUso($tipoGasto);

        $eliminado = $this->tiposGasto->eliminar($tipoGasto);

        $this->auditoria->registrarEliminacion(AuditoriaConstants::MODULO_GASTOS, "Tipo de gasto {$tipoGasto->codigo} eliminado", $tipoGasto);

        return $eliminado;
    }

    private function exigirSinGastos(TipoGasto $tipoGasto): void
    {
        if ($this->tiposGasto->tieneGastosAsociados($tipoGasto->id)) {
            throw ValidationException::withMessages([
                'tipo_gasto' => __('validation.custom.tipo_gasto.tiene_gastos_asociados'),
            ]);
        }
    }

    private function exigirSinUso(TipoGasto $tipoGasto): void
    {
        if ($this->tiposGasto->estaEnUsoPorConfiguracion($tipoGasto->id)) {
            throw ValidationException::withMessages([
                'tipo_gasto' => __('validation.custom.tipo_gasto.en_uso_por_configuracion'),
            ]);
        }
    }
}
