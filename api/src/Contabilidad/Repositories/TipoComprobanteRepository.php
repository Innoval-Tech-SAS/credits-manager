<?php

namespace App\Contabilidad\Repositories;

use App\Contabilidad\Models\TipoComprobante;
use App\Core\Base\BaseRepository;
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
