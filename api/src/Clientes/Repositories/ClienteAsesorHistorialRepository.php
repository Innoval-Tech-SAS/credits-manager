<?php

namespace App\Clientes\Repositories;

use App\Clientes\Models\ClienteAsesorHistorial;
use App\Core\Base\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

class ClienteAsesorHistorialRepository extends BaseRepository
{
    public function __construct(ClienteAsesorHistorial $historial)
    {
        parent::__construct($historial);
    }

    public function delCliente(int $clienteId): Collection
    {
        return $this->consulta()
            ->where('cliente_id', $clienteId)
            ->with(['asesor:id,nombre', 'asesorAnterior:id,nombre', 'autor:id,nombre'])
            ->orderByDesc('creado_en')
            ->orderByDesc('id')
            ->get();
    }
}
