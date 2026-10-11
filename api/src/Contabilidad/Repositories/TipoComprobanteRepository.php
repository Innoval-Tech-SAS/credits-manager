<?php

namespace App\Contabilidad\Repositories;

use App\Contabilidad\Models\Comprobante;
use App\Contabilidad\Models\ReglaContabilizacion;
use App\Contabilidad\Models\TipoComprobante;
use App\Core\Base\BaseRepository;
use App\Gastos\Models\ParametrizacionTipoGasto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TipoComprobanteRepository extends BaseRepository
{
    public function __construct(TipoComprobante $tipoComprobante)
    {
        parent::__construct($tipoComprobante);
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

    /** Regla de negocio "sin movimientos": el tipo ya tiene comprobantes contabilizados con ese número. */
    public function tieneComprobantesAsociados(int $tipoComprobanteId): bool
    {
        return Comprobante::where('tipo_comprobante_id', $tipoComprobanteId)->exists();
    }

    /** Evita que el `DELETE` reviente por el FK `RESTRICT` de reglas/parametrizaciones que usan este tipo. */
    public function estaEnUsoPorConfiguracion(int $tipoComprobanteId): bool
    {
        return ReglaContabilizacion::where('tipo_comprobante_id', $tipoComprobanteId)->exists()
            || ParametrizacionTipoGasto::where('tipo_comprobante_id', $tipoComprobanteId)->exists();
    }

    /**
     * Incrementa y devuelve el consecutivo en su propia transacción corta con lock,
     * ANTES de crear el comprobante — igual que `siguienteConsecutivo()` en el legado.
     * Si la creación del comprobante falla después, el número queda consumido (gaps
     * aceptables, estándar en numeración de comprobantes contables).
     */
    public function incrementarConsecutivo(int $tipoComprobanteId): int
    {
        return DB::transaction(function () use ($tipoComprobanteId) {
            $tipo = $this->consulta()->whereKey($tipoComprobanteId)->lockForUpdate()->firstOrFail();
            $tipo->consecutivo_actual += 1;
            $tipo->save();

            return $tipo->consecutivo_actual;
        });
    }
}
