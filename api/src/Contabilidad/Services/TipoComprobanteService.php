<?php

namespace App\Contabilidad\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Services\EmisorAuditoria;
use App\Contabilidad\Models\TipoComprobante;
use App\Contabilidad\Repositories\TipoComprobanteRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class TipoComprobanteService
{
    public function __construct(
        protected TipoComprobanteRepository $tipos,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function paginar(?string $busqueda, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->tipos->paginar($busqueda, max(1, min(100, $porPagina)), max(1, $pagina));
    }

    public function crear(array $datos): TipoComprobante
    {
        $tipo = $this->tipos->crear($datos);

        $this->auditoria->registrarCreacion(AuditoriaConstants::MODULO_CONTABILIDAD, "Tipo de comprobante {$tipo->codigo} {$tipo->nombre} creado", $tipo);

        return $tipo;
    }

    public function editar(TipoComprobante $tipo, array $datos): TipoComprobante
    {
        $anteriores = $this->auditoria->instantanea($tipo);
        $tipo = $this->tipos->actualizar($tipo, $datos);

        $this->auditoria->registrarModificacion(AuditoriaConstants::MODULO_CONTABILIDAD, "Tipo de comprobante {$tipo->codigo} modificado", $tipo, $anteriores);

        return $tipo;
    }

    public function eliminar(TipoComprobante $tipo): bool
    {
        $this->exigirSinComprobantes($tipo);
        $this->exigirSinUso($tipo);

        $eliminado = $this->tipos->eliminar($tipo);

        $this->auditoria->registrarEliminacion(AuditoriaConstants::MODULO_CONTABILIDAD, "Tipo de comprobante {$tipo->codigo} eliminado", $tipo);

        return $eliminado;
    }

    private function exigirSinComprobantes(TipoComprobante $tipo): void
    {
        if ($this->tipos->tieneComprobantesAsociados($tipo->id)) {
            throw ValidationException::withMessages([
                'tipo_comprobante' => __('validation.custom.tipo_comprobante.tiene_comprobantes_asociados'),
            ]);
        }
    }

    private function exigirSinUso(TipoComprobante $tipo): void
    {
        if ($this->tipos->estaEnUsoPorConfiguracion($tipo->id)) {
            throw ValidationException::withMessages([
                'tipo_comprobante' => __('validation.custom.tipo_comprobante.en_uso_por_configuracion'),
            ]);
        }
    }
}
