<?php

namespace App\Contabilidad\Repositories;

use App\Contabilidad\Models\AsientoContable;
use App\Contabilidad\Models\PlanCuenta;
use App\Contabilidad\Models\SaldoApertura;
use App\Contabilidad\Support\EfectoContable;
use App\Core\Base\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

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

    /** Padre de cada cuenta de la empresa, indexado por id: una sola consulta para recorrer la jerarquía en memoria. */
    public function mapaDePadres(): array
    {
        return $this->consulta()->pluck('cuenta_padre_id', 'id')->all();
    }

    public function tieneMovimientos(int $cuentaId): bool
    {
        return AsientoContable::where('cuenta_id', $cuentaId)->exists()
            || SaldoApertura::where('cuenta_id', $cuentaId)->exists();
    }

    public function porCodigos(array $codigos): Collection
    {
        return $this->consulta()->whereIn('codigo', $codigos)->get();
    }

    /** Balance de prueba: saldo de cada cuenta de detalle con movimiento hasta la fecha de corte. */
    public function saldosParaBalancePrueba(int $empresaId, string $hasta): Collection
    {
        return $this->consulta()
            ->joinSub($this->movimientosContabilizados($empresaId, null, $hasta), 'movimientos', 'movimientos.cuenta_id', '=', 'plan_cuentas.id')
            ->where('plan_cuentas.empresa_id', $empresaId)
            ->where('plan_cuentas.permite_movimiento', true)
            ->whereRaw('(movimientos.debitos > 0 OR movimientos.creditos > 0)')
            ->orderBy('plan_cuentas.codigo')
            ->selectRaw('plan_cuentas.codigo, plan_cuentas.nombre, plan_cuentas.naturaleza, plan_cuentas.tipo, movimientos.debitos, movimientos.creditos')
            ->get();
    }

    /**
     * Movimientos contabilizados agregados por cuenta antes de unirse al plan. Filtrar el comprobante en el ON de un LEFT
     * JOIN no basta: los asientos de borradores, revisados y anulados seguirían sumando en la cuenta.
     */
    private function movimientosContabilizados(int $empresaId, ?string $desde, string $hasta): QueryBuilder
    {
        $movimientos = DB::table('asientos_contables')
            ->join('comprobantes', 'comprobantes.id', '=', 'asientos_contables.comprobante_id')
            ->where('comprobantes.empresa_id', $empresaId)
            ->where(EfectoContable::condicion())
            ->groupBy('asientos_contables.cuenta_id')
            ->selectRaw('asientos_contables.cuenta_id, SUM(asientos_contables.debito) as debitos, SUM(asientos_contables.credito) as creditos');

        return $desde !== null
            ? $movimientos->whereBetween('comprobantes.fecha', [$desde, $hasta])
            : $movimientos->where('comprobantes.fecha', '<=', $hasta);
    }

    /**
     * Estados financieros: saldo por cuenta para un grupo de tipos. Con `$desde` filtra por
     * rango (P&G: ingresos/gastos del período); sin `$desde` filtra "hasta la fecha de corte"
     * (balance general: activo/pasivo/patrimonio, saldo acumulado).
     */
    public function saldosPorTipo(int $empresaId, array $tipos, ?string $desde, string $hasta): Collection
    {
        return $this->consulta()
            ->leftJoinSub($this->movimientosContabilizados($empresaId, $desde, $hasta), 'movimientos', 'movimientos.cuenta_id', '=', 'plan_cuentas.id')
            ->where('plan_cuentas.empresa_id', $empresaId)
            ->whereIn('plan_cuentas.tipo', $tipos)
            ->where('plan_cuentas.permite_movimiento', true)
            ->orderBy('plan_cuentas.codigo')
            ->selectRaw(
                'plan_cuentas.codigo, plan_cuentas.nombre, plan_cuentas.naturaleza, '.
                'COALESCE(movimientos.debitos,0) as d, COALESCE(movimientos.creditos,0) as c'
            )
            ->get();
    }
}
