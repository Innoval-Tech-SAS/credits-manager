<?php

namespace App\Clientes\Repositories;

use App\Clientes\Models\ClienteEmpleadorHistorial;
use App\Core\Base\BaseRepository;

class ClienteEmpleadorHistorialRepository extends BaseRepository
{
    public function __construct(ClienteEmpleadorHistorial $historial)
    {
        parent::__construct($historial);
    }

    /** Cierra el período vigente del cliente (si tiene uno) en la fecha dada. */
    public function cerrarVigente(int $clienteId, string $fecha): void
    {
        $this->consulta()
            ->where('cliente_id', $clienteId)
            ->whereNull('fecha_fin')
            ->update(['fecha_fin' => $fecha]);
    }
}
