<?php

namespace App\Contabilidad\Repositories;

use App\Contabilidad\Models\SaldoApertura;
use App\Core\Base\BaseRepository;

class SaldoAperturaRepository extends BaseRepository
{
    public function __construct(SaldoApertura $saldoApertura)
    {
        parent::__construct($saldoApertura);
    }
}
