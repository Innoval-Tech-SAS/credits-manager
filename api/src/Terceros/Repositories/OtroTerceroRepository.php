<?php

namespace App\Terceros\Repositories;

use App\Core\Base\BaseRepository;
use App\Terceros\Models\OtroTercero;

class OtroTerceroRepository extends BaseRepository
{
    public function __construct(OtroTercero $otroTercero)
    {
        parent::__construct($otroTercero);
    }
}
