<?php

namespace App\Pagos\Repositories;

use App\Core\Base\BaseRepository;
use App\Pagos\Models\ReversaPago;

class ReversaPagoRepository extends BaseRepository
{
    public function __construct(ReversaPago $reversa)
    {
        parent::__construct($reversa);
    }

    public function existePara(int $pagoId): bool
    {
        return $this->consulta()->where('pago_id', $pagoId)->exists();
    }
}
