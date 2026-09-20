<?php

namespace App\Notificaciones\Repositories;

use App\Core\Base\BaseRepository;
use App\Notificaciones\Models\Notificacion;
use Illuminate\Database\Eloquent\Collection;

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
