<?php

namespace App\Pagos\Repositories;

use App\Core\Base\BaseRepository;
use App\Pagos\Models\PagoDetalle;

class PagoDetalleRepository extends BaseRepository
{
    public function __construct(PagoDetalle $pagoDetalle)
    {
        parent::__construct($pagoDetalle);
    }
}
