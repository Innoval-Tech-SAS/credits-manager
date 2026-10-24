<?php

namespace App\Metas\Repositories;

use App\Core\Base\BaseRepository;
use App\Metas\Constants\MetasConstants;
use App\Metas\Models\MetaCierre;
use Illuminate\Database\Eloquent\Collection;

class CierreMetaRepository extends BaseRepository
{
    public function __construct(MetaCierre $cierre)
    {
        parent::__construct($cierre);
    }

    /**
     * Cierre vigente de cada meta: el más reciente por (meta, asesor), con el asesor nulo para la meta de empresa.
     *
     * @param  list<int>  $metaIds
     * @return Collection<int, MetaCierre>
     */
    public function vigentesDeMetas(int $empresaId, array $metaIds): Collection
    {
        if ($metaIds === []) {
            return new Collection;
        }

        return $this->consulta()
            ->where('empresa_id', $empresaId)
            ->whereIn('meta_id', $metaIds)
            ->orderByDesc('id')
            ->get()
            ->unique(fn (MetaCierre $cierre) => $cierre->meta_id.'-'.($cierre->asesor_id ?? 'empresa'))
            ->values();
    }

    /** @return list<int> */
    public function metaIdsCerradas(int $empresaId): array
    {
        return $this->consulta()->where('empresa_id', $empresaId)->distinct()->pluck('meta_id')->map(fn ($id) => (int) $id)->all();
    }

    public function tieneCierres(int $empresaId, int $metaId): bool
    {
        return $this->consulta()->where('empresa_id', $empresaId)->where('meta_id', $metaId)->exists();
    }

    /** Historial de cierres, más reciente primero, acotado para no crecer sin límite en una respuesta. */
    public function historial(int $empresaId, ?string $periodo, ?string $tipo): Collection
    {
        return $this->consulta()
            ->where('empresa_id', $empresaId)
            ->when($periodo !== null, fn ($consulta) => $consulta->where('periodo', $periodo))
            ->when($tipo !== null, fn ($consulta) => $consulta->where('tipo', $tipo))
            ->with('usuario:id,nombre')
            ->orderByDesc('id')
            ->limit(MetasConstants::LIMITE_ALERTAS * 4)
            ->get();
    }
}
