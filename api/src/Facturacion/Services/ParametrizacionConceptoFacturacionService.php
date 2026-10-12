<?php

namespace App\Facturacion\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Services\EmisorAuditoria;
use App\Facturacion\Enums\TipoConceptoFacturacion;
use App\Facturacion\Models\ParametrizacionConceptoFacturacion;
use App\Facturacion\Repositories\ParametrizacionConceptoFacturacionRepository;
use App\Usuarios\Models\Usuario;
use Illuminate\Support\Collection;

class ParametrizacionConceptoFacturacionService
{
    public function __construct(
        protected ParametrizacionConceptoFacturacionRepository $parametrizaciones,
        protected EmisorAuditoria $auditoria,
    ) {}

    /** @return Collection<string, ParametrizacionConceptoFacturacion> las 4 filas de la empresa, indexadas por tipo_concepto */
    public function listar(int $empresaId): Collection
    {
        return $this->parametrizaciones->paraEmpresa($empresaId);
    }

    public function actualizar(Usuario $usuario, TipoConceptoFacturacion $tipoConcepto, array $datos): ParametrizacionConceptoFacturacion
    {
        $fila = $this->parametrizaciones->deConcepto((int) $usuario->empresa_id, $tipoConcepto);

        if ($fila === null) {
            $fila = $this->parametrizaciones->crear(['empresa_id' => $usuario->empresa_id, 'tipo_concepto' => $tipoConcepto]);
        }

        $anteriores = $this->auditoria->instantanea($fila);

        $fila = $this->parametrizaciones->actualizar($fila, [...$datos, 'actualizado_por' => $usuario->id]);

        $this->auditoria->registrarModificacion(
            AuditoriaConstants::MODULO_FACTURACION,
            "Parametrización fiscal de «{$tipoConcepto->etiqueta()}» modificada",
            $fila,
            $anteriores,
        );

        return $fila;
    }
}
