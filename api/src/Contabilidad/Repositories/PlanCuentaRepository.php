<?php

namespace App\Contabilidad\Repositories;

use App\Contabilidad\Enums\EstadoComprobante;
use App\Contabilidad\Models\PlanCuenta;
use App\Core\Base\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class PlanCuentaRepository extends BaseRepository
{
    public function __construct(PlanCuenta $planCuenta)
    {
        parent::__construct($planCuenta);
    }

    /**
     * Sin paginar a propósito: el plan de cuentas es jerárquico (se indenta por `nivel`
     * bajo el código padre) y un LIMIT/OFFSET tradicional cortaría el árbol a la mitad.
     */
    public function todasOrdenadas(?string $busqueda = null): Collection
    {
        $consulta = $this->consulta()->orderBy('codigo');

        if ($busqueda) {
            $consulta->where(function (Builder $subconsulta) use ($busqueda) {
                $subconsulta->where('codigo', 'like', "%{$busqueda}%")
                    ->orWhere('nombre', 'like', "%{$busqueda}%");
            });
        }

        return $consulta->get();
    }

    public function deDetalle(): Collection
    {
        return $this->consulta()->where('permite_movimiento', true)->orderBy('codigo')->get();
    }

    public function deDetallePorTipo(array $tipos): Collection
    {
        return $this->consulta()->where('permite_movimiento', true)->whereIn('tipo', $tipos)->orderBy('codigo')->get();
    }

    /** Precarga de una sola vez las cuentas de varios códigos (evita una consulta por fila en importaciones). */
    /** La empresa va explícita: el libro mayor también corre en el worker de exportación, sin sesión. */
    public function buscarDeEmpresaOFallar(int $empresaId, int $id): PlanCuenta
    {
        return $this->consulta()->where('empresa_id', $empresaId)->findOrFail($id);
    }

    public function porCodigos(array $codigos): Collection
    {
        return $this->consulta()->whereIn('codigo', $codigos)->get();
    }

    /** Balance de prueba: saldo de cada cuenta de detalle con movimiento hasta la fecha de corte. */
    public function saldosParaBalancePrueba(int $empresaId, string $hasta): Collection
    {
        return $this->consulta()
            ->leftJoin('asientos_contables', 'asientos_contables.cuenta_id', '=', 'plan_cuentas.id')
            ->leftJoin('comprobantes', function ($join) use ($empresaId, $hasta) {
                $join->on('comprobantes.id', '=', 'asientos_contables.comprobante_id')
                    ->where('comprobantes.empresa_id', $empresaId)
                    ->where('comprobantes.estado', EstadoComprobante::Contabilizado->value)
                    ->where('comprobantes.fecha', '<=', $hasta);
            })
            ->where('plan_cuentas.empresa_id', $empresaId)
            ->where('plan_cuentas.permite_movimiento', true)
            ->groupBy('plan_cuentas.id', 'plan_cuentas.codigo', 'plan_cuentas.nombre', 'plan_cuentas.naturaleza', 'plan_cuentas.tipo')
            ->havingRaw('COALESCE(SUM(asientos_contables.debito),0) > 0 OR COALESCE(SUM(asientos_contables.credito),0) > 0')
            ->orderBy('plan_cuentas.codigo')
            ->selectRaw(
                'plan_cuentas.codigo, plan_cuentas.nombre, plan_cuentas.naturaleza, plan_cuentas.tipo, '.
                'COALESCE(SUM(asientos_contables.debito),0) as debitos, COALESCE(SUM(asientos_contables.credito),0) as creditos'
            )
            ->get();
    }

    /**
     * Estados financieros: saldo por cuenta para un grupo de tipos. Con `$desde` filtra por
     * rango (P&G: ingresos/gastos del período); sin `$desde` filtra "hasta la fecha de corte"
     * (balance general: activo/pasivo/patrimonio, saldo acumulado).
     */
    public function saldosPorTipo(int $empresaId, array $tipos, ?string $desde, string $hasta): Collection
    {
        return $this->consulta()
            ->leftJoin('asientos_contables', 'asientos_contables.cuenta_id', '=', 'plan_cuentas.id')
            ->leftJoin('comprobantes', function ($join) use ($empresaId, $desde, $hasta) {
                $join->on('comprobantes.id', '=', 'asientos_contables.comprobante_id')
                    ->where('comprobantes.empresa_id', $empresaId)
                    ->where('comprobantes.estado', EstadoComprobante::Contabilizado->value);
                if ($desde !== null) {
                    $join->whereBetween('comprobantes.fecha', [$desde, $hasta]);
                } else {
                    $join->where('comprobantes.fecha', '<=', $hasta);
                }
            })
            ->where('plan_cuentas.empresa_id', $empresaId)
            ->whereIn('plan_cuentas.tipo', $tipos)
            ->where('plan_cuentas.permite_movimiento', true)
            ->groupBy('plan_cuentas.id', 'plan_cuentas.codigo', 'plan_cuentas.nombre', 'plan_cuentas.naturaleza')
            ->orderBy('plan_cuentas.codigo')
            ->selectRaw(
                'plan_cuentas.codigo, plan_cuentas.nombre, plan_cuentas.naturaleza, '.
                'COALESCE(SUM(asientos_contables.debito),0) as d, COALESCE(SUM(asientos_contables.credito),0) as c'
            )
            ->get();
    }
}
