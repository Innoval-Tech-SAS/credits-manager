<?php

namespace App\Contabilidad\Repositories;

use App\Contabilidad\Models\AplicacionDocumento;
use App\Core\Base\BaseRepository;

class AplicacionDocumentoRepository extends BaseRepository
{
    public function __construct(AplicacionDocumento $aplicacionDocumento)
    {
        parent::__construct($aplicacionDocumento);
    }
}
