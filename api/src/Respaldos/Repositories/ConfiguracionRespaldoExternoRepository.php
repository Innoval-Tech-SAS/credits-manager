<?php

namespace App\Respaldos\Repositories;

use App\Core\Base\BaseRepository;
use App\Respaldos\Models\ConfiguracionRespaldoExterno;

class ConfiguracionRespaldoExternoRepository extends BaseRepository
{
    public function __construct(ConfiguracionRespaldoExterno $configuracion)
    {
        parent::__construct($configuracion);
    }

    /** Siempre hay exactamente una fila, sembrada por la migración. */
    public function obtener(): ConfiguracionRespaldoExterno
    {
        return $this->consulta()->firstOrFail();
    }
}
