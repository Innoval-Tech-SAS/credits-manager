<?php

namespace App\Monitoreo\Repositories;

use App\Core\Base\BaseRepository;
use App\Monitoreo\Models\TrabajoFallido;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TrabajoFallidoRepository extends BaseRepository
{
    public function __construct(TrabajoFallido $modelo)
    {
        parent::__construct($modelo);
    }

    public function paginar(?string $cola, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->consulta()
            ->select(['id', 'uuid', 'queue', 'payload', 'exception', 'failed_at'])
            ->when($cola !== null, fn ($consulta) => $consulta->where('queue', $cola))
            ->orderByDesc('failed_at')
            ->paginate($porPagina, ['*'], 'page', $pagina);
    }

    /** @return array<string, int> fallidos por cola */
    public function contarPorCola(): array
    {
        return $this->consulta()
            ->selectRaw('queue, count(*) as total')
            ->groupBy('queue')
            ->pluck('total', 'queue')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    public function buscarPorUuid(string $uuid): ?TrabajoFallido
    {
        return $this->consulta()->select(['id', 'uuid', 'queue', 'payload'])->where('uuid', $uuid)->first();
    }
}
