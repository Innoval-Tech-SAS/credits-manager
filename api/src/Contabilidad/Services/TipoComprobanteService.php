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

    /** Para que el frontend deshabilite `consecutivo_inicial` con una pista, en vez de que el usuario choque con un 422. */
    public function tieneComprobantesAsociados(TipoComprobante $tipo): bool
    {
        return $this->tipos->tieneComprobantesAsociados($tipo->id);
    }

    public function editar(TipoComprobante $tipo, array $datos): TipoComprobante
    {
        $this->validarConfiguracionConsecutivo($tipo, $datos);

        $anteriores = $this->auditoria->instantanea($tipo);
        $tipo = $this->tipos->actualizar($tipo, $datos);

        $this->auditoria->registrarModificacion(AuditoriaConstants::MODULO_CONTABILIDAD, "Tipo de comprobante {$tipo->codigo} modificado", $tipo, $anteriores);

        return $tipo;
    }

    /**
     * El inicial no se puede mover si ya hay comprobantes (reescribiría el punto de partida de números ya
     * usados). El final, si se define, nunca puede quedar por debajo del inicial ni de lo ya consumido
     * (`consecutivo_actual`): ambos dejarían el tipo en un estado "agotado" de forma inconsistente.
     */
    private function validarConfiguracionConsecutivo(TipoComprobante $tipo, array $datos): void
    {
        if (array_key_exists('consecutivo_inicial', $datos)
            && $datos['consecutivo_inicial'] !== $tipo->consecutivo_inicial
            && $this->tipos->tieneComprobantesAsociados($tipo->id)) {
            throw ValidationException::withMessages([
                'consecutivo_inicial' => __('validation.custom.tipo_comprobante.consecutivo_inicial_bloqueado'),
            ]);
        }

        $consecutivoInicial = $datos['consecutivo_inicial'] ?? $tipo->consecutivo_inicial;
        $consecutivoFinal = array_key_exists('consecutivo_final', $datos) ? $datos['consecutivo_final'] : $tipo->consecutivo_final;

        if ($consecutivoFinal !== null && ($consecutivoFinal < $consecutivoInicial || $consecutivoFinal < $tipo->consecutivo_actual)) {
            throw ValidationException::withMessages([
                'consecutivo_final' => __('validation.custom.tipo_comprobante.rango_invalido'),
            ]);
        }
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
