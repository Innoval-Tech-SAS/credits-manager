<?php

namespace App\Contabilidad\Repositories;

use App\Contabilidad\Models\AsientoContable;
use App\Contabilidad\Support\EfectoContable;
use App\Core\Base\BaseRepository;
use App\Core\Dinero\Dinero;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

class AsientoContableRepository extends BaseRepository
{
    public function __construct(AsientoContable $asientoContable)
    {
        parent::__construct($asientoContable);
    }

    public function crearVarias(int $comprobanteId, array $lineas): void
    {
        $ahora = now();
        $filas = [];

        foreach ($lineas as $orden => $linea) {
            $filas[] = [
                'comprobante_id' => $comprobanteId,
                'cuenta_id' => $linea['cuenta_id'],
                'tercero_id' => $linea['tercero_id'] ?? null,
                'centro_costo_id' => $linea['centro_costo_id'] ?? null,
                'documento_cruce' => $linea['documento_cruce'] ?? null,
                'fecha_vencimiento' => $linea['fecha_vencimiento'] ?? null,
                'debito' => self::centavos($linea['debito'] ?? 0),
                'credito' => self::centavos($linea['credito'] ?? 0),
                'descripcion' => $linea['descripcion'] ?? null,
                'orden' => $orden,
                'creado_en' => $ahora,
                'ultima_actualizacion' => $ahora,
            ];
        }

        AsientoContable::insert($filas);
    }

    private static function centavos(Dinero|int $valor): int
    {
        return $valor instanceof Dinero ? $valor->centavos() : $valor;
    }

    /**
     * La empresa se recibe explícita: los reportes también corren en el worker de exportación, sin sesión,
     * donde el scope de empresa no filtra.
     */
    private function consultaLibroDiario(int $empresaId, string $desde, string $hasta): Builder
    {
        return $this->consulta()
            ->join('comprobantes', 'comprobantes.id', '=', 'asientos_contables.comprobante_id')
            ->join('tipos_comprobante', 'tipos_comprobante.id', '=', 'comprobantes.tipo_comprobante_id')
            ->join('plan_cuentas', 'plan_cuentas.id', '=', 'asientos_contables.cuenta_id')
            ->leftJoin('terceros', 'terceros.id', '=', 'asientos_contables.tercero_id')
            ->where('comprobantes.empresa_id', $empresaId)
            ->where(EfectoContable::condicion())
            ->whereBetween('comprobantes.fecha', [$desde, $hasta]);
    }

    private function filasLibroDiario(int $empresaId, string $desde, string $hasta): Builder
    {
        return $this->consultaLibroDiario($empresaId, $desde, $hasta)
            ->orderBy('comprobantes.fecha')
            ->orderBy('comprobantes.id')
            ->orderBy('asientos_contables.orden')
            ->select([
                'comprobantes.fecha',
                'comprobantes.consecutivo',
                'comprobantes.ciclo_numeracion',
                'tipos_comprobante.codigo as tipo',
                'plan_cuentas.codigo as cuenta_codigo',
                'plan_cuentas.nombre as cuenta_nombre',
                'asientos_contables.descripcion',
                'asientos_contables.debito',
                'asientos_contables.credito',
                'terceros.nombre as tercero',
            ]);
    }

    public function movimientosLibroDiario(int $empresaId, string $desde, string $hasta): Collection
    {
        return $this->filasLibroDiario($empresaId, $desde, $hasta)->get();
    }

    public function cursorLibroDiario(int $empresaId, string $desde, string $hasta): LazyCollection
    {
        return $this->filasLibroDiario($empresaId, $desde, $hasta)->lazy();
    }

    public function contarLibroDiario(int $empresaId, string $desde, string $hasta): int
    {
        return $this->consultaLibroDiario($empresaId, $desde, $hasta)->count();
    }

    /** @return array{debitos: int, creditos: int} */
    public function totalesLibroDiario(int $empresaId, string $desde, string $hasta): array
    {
        $totales = $this->consultaLibroDiario($empresaId, $desde, $hasta)
            ->selectRaw('COALESCE(SUM(asientos_contables.debito),0) as debitos, COALESCE(SUM(asientos_contables.credito),0) as creditos')
            ->first();

        return ['debitos' => (int) $totales->debitos, 'creditos' => (int) $totales->creditos];
    }

    /** @return array{debitos: int, creditos: int} */
    public function saldoAntesDeFecha(int $empresaId, int $cuentaId, string $fecha): array
    {
        $totales = $this->consulta()
            ->join('comprobantes', 'comprobantes.id', '=', 'asientos_contables.comprobante_id')
            ->where('asientos_contables.cuenta_id', $cuentaId)
            ->where('comprobantes.empresa_id', $empresaId)
            ->where(EfectoContable::condicion())
            ->where('comprobantes.fecha', '<', $fecha)
            ->selectRaw('COALESCE(SUM(asientos_contables.debito),0) as debitos, COALESCE(SUM(asientos_contables.credito),0) as creditos')
            ->first();

        return ['debitos' => (int) $totales->debitos, 'creditos' => (int) $totales->creditos];
    }

    private function consultaLibroMayor(int $empresaId, int $cuentaId, string $desde, string $hasta): Builder
    {
        return $this->consulta()
            ->join('comprobantes', 'comprobantes.id', '=', 'asientos_contables.comprobante_id')
            ->join('tipos_comprobante', 'tipos_comprobante.id', '=', 'comprobantes.tipo_comprobante_id')
            ->leftJoin('terceros', 'terceros.id', '=', 'asientos_contables.tercero_id')
            ->where('asientos_contables.cuenta_id', $cuentaId)
            ->where('comprobantes.empresa_id', $empresaId)
            ->where(EfectoContable::condicion())
            ->whereBetween('comprobantes.fecha', [$desde, $hasta]);
    }

    private function filasLibroMayor(int $empresaId, int $cuentaId, string $desde, string $hasta): Builder
    {
        return $this->consultaLibroMayor($empresaId, $cuentaId, $desde, $hasta)
            ->orderBy('comprobantes.fecha')
            ->orderBy('comprobantes.id')
            ->orderBy('asientos_contables.orden')
            ->select([
                'comprobantes.fecha',
                'comprobantes.consecutivo',
                'comprobantes.ciclo_numeracion',
                'tipos_comprobante.codigo as tipo',
                'asientos_contables.descripcion',
                'asientos_contables.debito',
                'asientos_contables.credito',
                'terceros.nombre as tercero',
            ]);
    }

    public function movimientosLibroMayor(int $empresaId, int $cuentaId, string $desde, string $hasta): Collection
    {
        return $this->filasLibroMayor($empresaId, $cuentaId, $desde, $hasta)->get();
    }

    public function cursorLibroMayor(int $empresaId, int $cuentaId, string $desde, string $hasta): LazyCollection
    {
        return $this->filasLibroMayor($empresaId, $cuentaId, $desde, $hasta)->lazy();
    }

    public function contarLibroMayor(int $empresaId, int $cuentaId, string $desde, string $hasta): int
    {
        return $this->consultaLibroMayor($empresaId, $cuentaId, $desde, $hasta)->count();
    }

    /** Base de los auxiliares: solo asientos de comprobantes contabilizados de la empresa, filtrables por cuenta y tercero. */
    private function consultaAuxiliar(int $empresaId, ?int $cuentaId, ?int $terceroId): Builder
    {
        return $this->consulta()
            ->join('comprobantes', 'comprobantes.id', '=', 'asientos_contables.comprobante_id')
            ->join('tipos_comprobante', 'tipos_comprobante.id', '=', 'comprobantes.tipo_comprobante_id')
            ->join('plan_cuentas', 'plan_cuentas.id', '=', 'asientos_contables.cuenta_id')
            ->leftJoin('terceros', 'terceros.id', '=', 'asientos_contables.tercero_id')
            ->where('comprobantes.empresa_id', $empresaId)
            ->where(EfectoContable::condicion())
            ->when($cuentaId, fn (Builder $consulta) => $consulta->where('asientos_contables.cuenta_id', $cuentaId))
            ->when($terceroId, fn (Builder $consulta) => $consulta->where('asientos_contables.tercero_id', $terceroId));
    }

    /**
     * Movimientos del rango, agrupables por cuenta. Lleva los datos de la cuenta (código, nombre y naturaleza) para
     * que el auxiliar no vuelva a consultarla por cada fila.
     */
    public function movimientosAuxiliar(int $empresaId, string $desde, string $hasta, ?int $cuentaId, ?int $terceroId): Collection
    {
        return $this->consultaAuxiliar($empresaId, $cuentaId, $terceroId)
            ->whereBetween('comprobantes.fecha', [$desde, $hasta])
            ->orderBy('plan_cuentas.codigo')
            ->orderBy('comprobantes.fecha')
            ->orderBy('comprobantes.id')
            ->orderBy('asientos_contables.orden')
            ->select([
                'asientos_contables.cuenta_id',
                'plan_cuentas.codigo as cuenta_codigo',
                'plan_cuentas.nombre as cuenta_nombre',
                'plan_cuentas.naturaleza as cuenta_naturaleza',
                'comprobantes.fecha',
                'comprobantes.consecutivo',
                'comprobantes.ciclo_numeracion',
                'tipos_comprobante.codigo as tipo',
                'asientos_contables.descripcion',
                'asientos_contables.debito',
                'asientos_contables.credito',
                'terceros.nombre as tercero',
            ])
            ->get();
    }

    /**
     * Asientos de una cuenta en el rango que todavía no tienen una marca de conciliación vigente (conciliado
     * sin reversa): base del lado "libro" para sugerir candidatos de conciliación bancaria.
     */
    public function candidatosConciliables(int $empresaId, int $cuentaId, string $desde, string $hasta): Collection
    {
        return $this->consultaAuxiliar($empresaId, $cuentaId, null)
            ->whereBetween('comprobantes.fecha', [$desde, $hasta])
            ->whereNotExists(function (QueryBuilder $consulta) {
                $consulta->selectRaw('1')
                    ->from('marcas_conciliacion as mc')
                    ->whereColumn('mc.asiento_contable_id', 'asientos_contables.id')
                    ->where('mc.tipo', 'conciliado')
                    ->whereNotExists(function (QueryBuilder $reversa) {
                        $reversa->selectRaw('1')->from('marcas_conciliacion as r')->whereColumn('r.marca_origen_id', 'mc.id');
                    });
            })
            ->orderBy('comprobantes.fecha')
            ->select([
                'asientos_contables.id',
                'comprobantes.id as comprobante_id',
                'comprobantes.fecha',
                'comprobantes.consecutivo',
                'comprobantes.modulo_origen',
                'comprobantes.origen_id',
                'tipos_comprobante.codigo as tipo_comprobante',
                'asientos_contables.descripcion',
                'asientos_contables.debito',
                'asientos_contables.credito',
            ])
            ->get();
    }

    /**
     * Asientos de caja/bancos de egresos y cobros contabilizados en el rango que todavía no tienen una marca de
     * conciliación vigente — "cada egreso y cada recibo deben aparecer en el extracto" (6.6.3). Mismo patrón de
     * `whereNotExists` que `candidatosConciliables()`, pero por prefijo de cuenta y módulo de origen, no por una
     * sola cuenta: ver `ControlesCierreService`.
     *
     * @param  list<string>  $prefijosCuenta
     * @param  list<string>  $modulosOrigen
     */
    public function sinConciliarPorModuloOrigen(int $empresaId, array $prefijosCuenta, array $modulosOrigen, string $desde, string $hasta): Collection
    {
        return $this->consultaAuxiliar($empresaId, null, null)
            ->whereBetween('comprobantes.fecha', [$desde, $hasta])
            ->whereIn('comprobantes.modulo_origen', $modulosOrigen)
            ->where(function (Builder $query) use ($prefijosCuenta) {
                foreach ($prefijosCuenta as $prefijo) {
                    $query->orWhere('plan_cuentas.codigo', 'like', "{$prefijo}%");
                }
            })
            ->whereNotExists(function (QueryBuilder $consulta) {
                $consulta->selectRaw('1')
                    ->from('marcas_conciliacion as mc')
                    ->whereColumn('mc.asiento_contable_id', 'asientos_contables.id')
                    ->where('mc.tipo', 'conciliado')
                    ->whereNotExists(function (QueryBuilder $reversa) {
                        $reversa->selectRaw('1')->from('marcas_conciliacion as r')->whereColumn('r.marca_origen_id', 'mc.id');
                    });
            })
            ->orderBy('comprobantes.fecha')
            ->select([
                'asientos_contables.id',
                'comprobantes.id as comprobante_id',
                'comprobantes.fecha',
                'comprobantes.consecutivo',
                'comprobantes.modulo_origen',
                'comprobantes.origen_id',
                'plan_cuentas.codigo as cuenta_codigo',
                'asientos_contables.debito',
                'asientos_contables.credito',
            ])
            ->get();
    }

    /**
     * Saldo previo al rango por cuenta, en centavos. Devuelve también los datos de la cuenta para las cuentas que solo
     * tienen saldo anterior y ningún movimiento dentro del rango.
     */
    public function saldosAuxiliarAntesDe(int $empresaId, string $desde, ?int $cuentaId, ?int $terceroId): Collection
    {
        return $this->consultaAuxiliar($empresaId, $cuentaId, $terceroId)
            ->where('comprobantes.fecha', '<', $desde)
            ->groupBy('asientos_contables.cuenta_id', 'plan_cuentas.codigo', 'plan_cuentas.nombre', 'plan_cuentas.naturaleza')
            ->selectRaw('asientos_contables.cuenta_id, plan_cuentas.codigo as cuenta_codigo, plan_cuentas.nombre as cuenta_nombre, plan_cuentas.naturaleza as cuenta_naturaleza, COALESCE(SUM(asientos_contables.debito),0) as debitos, COALESCE(SUM(asientos_contables.credito),0) as creditos')
            ->get();
    }
}
