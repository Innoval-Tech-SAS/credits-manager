<?php

namespace App\Notificaciones\Repositories;

use App\Core\Base\BaseRepository;
use App\Notificaciones\Models\Notificacion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificacionRepository extends BaseRepository
{
    public function __construct(Notificacion $notificacion)
    {
        parent::__construct($notificacion);
    }

    public function recientesDelUsuario(int $usuarioId, int $limite): Collection
    {
        return $this->consulta()
            ->where('usuario_id', $usuarioId)
            ->orderByDesc('id')
            ->limit($limite)
            ->get();
    }

    /** @param  string|null  $leida  'leidas', 'no_leidas' o null (todas) */
    public function paginar(int $usuarioId, ?string $tipo, ?string $leida, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->consulta()
            ->where('usuario_id', $usuarioId)
            ->when($tipo, fn ($consulta, $valor) => $consulta->where('tipo', $valor))
            ->when($leida === 'leidas', fn ($consulta) => $consulta->whereNotNull('leida_en'))
            ->when($leida === 'no_leidas', fn ($consulta) => $consulta->whereNull('leida_en'))
            ->orderByDesc('id')
            ->paginate(perPage: $porPagina, page: $pagina);
    }

    public function contarNoLeidas(int $usuarioId): int
    {
        return $this->consulta()->where('usuario_id', $usuarioId)->whereNull('leida_en')->count();
    }

    public function buscarDelUsuarioOFallar(int $id, int $usuarioId): Notificacion
    {
        return $this->consulta()->where('usuario_id', $usuarioId)->findOrFail($id);
    }

    public function marcarTodasLeidas(int $usuarioId): int
    {
        return $this->consulta()
            ->where('usuario_id', $usuarioId)
            ->whereNull('leida_en')
            ->update(['leida_en' => now(), 'ultima_actualizacion' => now()]);
    }
}
