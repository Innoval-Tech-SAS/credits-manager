<?php

namespace App\Recibos\Repositories;

use App\Core\Base\BaseRepository;
use App\Recibos\Models\ReciboCaja;

class ReciboCajaRepository extends BaseRepository
{
    public function __construct(ReciboCaja $recibo)
    {
        parent::__construct($recibo);
    }

    public function porPago(int $pagoId): ?ReciboCaja
    {
        return $this->consulta()->where('pago_id', $pagoId)->first();
    }
}
