<?php

namespace App\Contabilidad\Repositories;

use App\Contabilidad\Enums\TipoCuenta;
use App\Contabilidad\Models\AsientoContable;
use App\Contabilidad\Models\CentroCosto;
use App\Contabilidad\Support\EfectoContable;
use App\Core\Base\BaseRepository;
use App\Gastos\Models\Gasto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class CentroCostoRepository extends BaseRepository
{
    public function __construct(CentroCosto $centroCosto)
    {
        parent::__construct($centroCosto);
    }

    public function activos(): Collection
    {
        return $this->consulta()->where('activo', true)->orderBy('nombre')->get();
    }

    public function paginar(?string $busqueda, int $porPagina, int $pagina): LengthAwarePaginator
    {
        $consulta = $this->consulta()->orderBy('codigo');

        if ($busqueda) {
            $consulta->where(function (Builder $subconsulta) use ($busqueda) {
                $subconsulta->where('codigo', 'like', "%{$busqueda}%")
                    ->orWhere('nombre', 'like', "%{$busqueda}%");
            });
        }

        return $consulta->paginate(perPage: $porPagina, page: $pagina);
    }

    /** Regla de negocio "sin movimientos": el centro ya tiene gastos causados o asientos contabilizados. */
    public function tieneMovimientosAsociados(int $centroCostoId): bool
    {
        return Gasto::where('centro_costo_id', $centroCostoId)->exists()
            || AsientoContable::where('centro_costo_id', $centroCostoId)->exists();
    }

    /**
     * Movimientos del centro (gastos causados + asientos contabilizados) en una sola línea de tiempo.
     * `DB::table()` en vez de los modelos Eloquent a propósito: paginar un UNION de dos modelos distintos los
     * hidrataría todos como el primero, y el cast de `estado` de `Gasto` reventaría con los valores de
     * `comprobantes.estado`. Al no pasar por los modelos, ninguna de las dos tablas trae el scope de empresa
     * ni el de soft delete — ambos se repiten a mano en cada lado del UNION.
     */
    public function paginarMovimientos(int $empresaId, int $centroCostoId, int $porPagina, int $pagina): LengthAwarePaginator
    {
        $gastos = DB::table('gastos')
            ->where('centro_costo_id', $centroCostoId)
            ->where('empresa_id', $empresaId)
            ->whereNull('borrado_en')
            ->selectRaw("id, 'gasto' as tipo, fecha, numero_documento, null as tipo_codigo, null as ciclo_numeracion, null as consecutivo, concepto as descripcion, valor_neto as debito, 0 as credito, estado");

        $asientos = DB::table('asientos_contables')
            ->join('comprobantes', 'comprobantes.id', '=', 'asientos_contables.comprobante_id')
            ->join('tipos_comprobante', 'tipos_comprobante.id', '=', 'comprobantes.tipo_comprobante_id')
            ->where('asientos_contables.centro_costo_id', $centroCostoId)
            ->where('comprobantes.empresa_id', $empresaId)
            ->whereNull('asientos_contables.borrado_en')
            ->whereNull('comprobantes.borrado_en')
            ->where(EfectoContable::condicion())
            ->selectRaw('asientos_contables.id, \'asiento\' as tipo, comprobantes.fecha, null as numero_documento, tipos_comprobante.codigo as tipo_codigo, comprobantes.ciclo_numeracion, comprobantes.consecutivo, asientos_contables.descripcion, asientos_contables.debito, asientos_contables.credito, comprobantes.estado');

        return $gastos->unionAll($asientos)
            ->orderByDesc('fecha')
            ->paginate(perPage: $porPagina, page: $pagina);
    }

    /**
     * Gastos y costos contabilizados del rango, agrupados por centro de costo (un grupo "Sin centro de costo"
     * agrupa los asientos sin `centro_costo_id`). `plan_cuentas` y `centros_costo` se filtran por empresa a mano:
     * el `join`/`leftJoin` no hereda el scope de la tabla base.
     */
    public function gastosPorCentroCosto(int $empresaId, string $desde, string $hasta): SupportCollection
    {
        return DB::table('asientos_contables')
            ->join('comprobantes', 'comprobantes.id', '=', 'asientos_contables.comprobante_id')
            ->join('plan_cuentas', 'plan_cuentas.id', '=', 'asientos_contables.cuenta_id')
            ->leftJoin('centros_costo', function (JoinClause $join) use ($empresaId) {
                $join->on('centros_costo.id', '=', 'asientos_contables.centro_costo_id')->where('centros_costo.empresa_id', $empresaId);
            })
            ->where('comprobantes.empresa_id', $empresaId)
            ->where('plan_cuentas.empresa_id', $empresaId)
            ->whereIn('plan_cuentas.tipo', [TipoCuenta::Gasto->value, TipoCuenta::Costo->value])
            ->whereBetween('comprobantes.fecha', [$desde, $hasta])
            ->where(EfectoContable::condicion())
            ->groupBy('asientos_contables.centro_costo_id', 'centros_costo.nombre')
            ->selectRaw(
                "asientos_contables.centro_costo_id, COALESCE(centros_costo.nombre, 'Sin centro de costo') as nombre, ".
                'SUM(asientos_contables.debito - asientos_contables.credito) as valor'
            )
            ->orderByDesc('valor')
            ->get();
    }
}
