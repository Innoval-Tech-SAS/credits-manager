<?php

namespace App\Contabilidad\Repositories;

use App\Contabilidad\Enums\EstadoComprobante;
use App\Contabilidad\Models\AsientoContable;
use App\Core\Base\BaseRepository;
use App\Core\Dinero\Dinero;
use Illuminate\Database\Eloquent\Builder;
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
            ->where('comprobantes.empresa_id', $empresaId)
            ->where('comprobantes.estado', EstadoComprobante::Contabilizado)
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
                'tipos_comprobante.codigo as tipo',
                'plan_cuentas.codigo as cuenta_codigo',
                'plan_cuentas.nombre as cuenta_nombre',
                'asientos_contables.descripcion',
                'asientos_contables.debito',
                'asientos_contables.credito',
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
            ->where('comprobantes.estado', EstadoComprobante::Contabilizado)
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
            ->where('comprobantes.estado', EstadoComprobante::Contabilizado)
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
}
