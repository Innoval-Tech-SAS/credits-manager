<?php

namespace App\Contabilidad\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Services\EmisorAuditoria;
use App\Contabilidad\Enums\CodigoDocumentoNegocio;
use App\Contabilidad\Models\TipoDocumentoNegocio;
use App\Contabilidad\Repositories\TipoDocumentoNegocioRepository;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TipoDocumentoNegocioService
{
    public function __construct(
        protected TipoDocumentoNegocioRepository $tipos,
        protected EmisorAuditoria $auditoria,
    ) {}

    /** @return Collection<int, TipoDocumentoNegocio> */
    public function listar(int $empresaId): Collection
    {
        return $this->tipos->paraEmpresa($empresaId);
    }

    public function actualizar(TipoDocumentoNegocio $tipo, array $datos): TipoDocumentoNegocio
    {
        $anteriores = $this->auditoria->instantanea($tipo);
        $tipo = $this->tipos->actualizar($tipo, $datos);

        $this->auditoria->registrarModificacion(
            AuditoriaConstants::MODULO_CONTABILIDAD,
            "Tipo de documento de negocio {$tipo->codigo->value} modificado",
            $tipo,
            $anteriores,
        );

        return $tipo;
    }

    /**
     * Punto único donde cada flujo de negocio (Facturación, Recibos, Egresos, Gastos/Causación) confirma, justo
     * antes de crear o cruzar un `DocumentoTercero`, que el catálogo configurado para ese tipo de documento sigue
     * permitiendo la operación que está a punto de hacer. Si una empresa desactivó el tipo o invirtió sus flags,
     * la operación falla aquí (422) en vez de dejar datos contables inconsistentes.
     */
    public function validarOperacion(int $empresaId, CodigoDocumentoNegocio $codigo, bool $creaSaldo, bool $cruzaSaldo): void
    {
        $tipo = $this->tipos->buscarPorCodigo($empresaId, $codigo);

        if ($tipo === null || ! $tipo->activo) {
            throw ValidationException::withMessages([
                'documento' => __('validation.custom.tipo_documento_negocio.inactivo', ['codigo' => $codigo->value]),
            ]);
        }

        if ($tipo->crea_saldo !== $creaSaldo || $tipo->cruza_saldo !== $cruzaSaldo) {
            throw ValidationException::withMessages([
                'documento' => __('validation.custom.tipo_documento_negocio.operacion_no_permitida', ['codigo' => $codigo->value]),
            ]);
        }
    }
}
