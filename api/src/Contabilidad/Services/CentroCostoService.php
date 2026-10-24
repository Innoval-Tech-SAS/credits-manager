<?php

namespace App\Contabilidad\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Services\EmisorAuditoria;
use App\Contabilidad\Models\CentroCosto;
use App\Contabilidad\Repositories\CentroCostoRepository;
use App\Contabilidad\Support\NumeroComprobante;
use App\Core\Dinero\Dinero;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class CentroCostoService
{
    public function __construct(
        protected CentroCostoRepository $centros,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function paginar(?string $busqueda, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->centros->paginar($busqueda, max(1, min(100, $porPagina)), max(1, $pagina));
    }

    public function crear(array $datos): CentroCosto
    {
        $centro = $this->centros->crear($datos);

        $this->auditoria->registrarCreacion(AuditoriaConstants::MODULO_CONTABILIDAD, "Centro de costo {$centro->codigo} {$centro->nombre} creado", $centro);

        return $centro;
    }

    /** Para que el frontend deshabilite la eliminación con una pista, en vez de que el usuario choque con un 422. */
    public function tieneMovimientosAsociados(CentroCosto $centro): bool
    {
        return $this->centros->tieneMovimientosAsociados($centro->id);
    }

    public function movimientos(CentroCosto $centro, int $empresaId, int $porPagina, int $pagina): LengthAwarePaginator
    {
        $movimientos = $this->centros->paginarMovimientos($empresaId, $centro->id, max(1, min(100, $porPagina)), max(1, $pagina));

        $movimientos->getCollection()->transform(fn (object $fila) => [
            'id' => "{$fila->tipo}-{$fila->id}",
            'tipo' => $fila->tipo,
            'fecha' => $fila->fecha,
            'documento' => $fila->tipo === 'gasto'
                ? $fila->numero_documento
                : NumeroComprobante::formatear($fila->tipo_codigo, $fila->ciclo_numeracion ?? '', (int) $fila->consecutivo),
            'descripcion' => $fila->descripcion,
            'debito' => Dinero::desdeCentavos((int) $fila->debito)->aDecimalString(),
            'credito' => Dinero::desdeCentavos((int) $fila->credito)->aDecimalString(),
            'estado' => $fila->estado,
        ]);

        return $movimientos;
    }

    public function editar(CentroCosto $centro, array $datos): CentroCosto
    {
        $anteriores = $this->auditoria->instantanea($centro);
        $centro = $this->centros->actualizar($centro, $datos);

        $this->auditoria->registrarModificacion(AuditoriaConstants::MODULO_CONTABILIDAD, "Centro de costo {$centro->codigo} modificado", $centro, $anteriores);

        return $centro;
    }

    public function eliminar(CentroCosto $centro): bool
    {
        if ($this->centros->tieneMovimientosAsociados($centro->id)) {
            throw ValidationException::withMessages([
                'centro_costo' => __('validation.custom.centro_costo.tiene_movimientos_asociados'),
            ]);
        }

        $eliminado = $this->centros->eliminar($centro);

        $this->auditoria->registrarEliminacion(AuditoriaConstants::MODULO_CONTABILIDAD, "Centro de costo {$centro->codigo} eliminado", $centro);

        return $eliminado;
    }
}
