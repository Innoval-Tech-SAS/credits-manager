<?php

namespace App\Correo\Repositories;

use App\Core\Base\BaseRepository;
use App\Correo\Models\ConfiguracionCorreo;

class ConfiguracionCorreoRepository extends BaseRepository
{
    public function __construct(ConfiguracionCorreo $configuracion)
    {
        parent::__construct($configuracion);
    }

    /** Explícito por empresa: los jobs y comandos no tienen sesión, así que el scope no filtra por ellos. */
    public function deEmpresa(int $empresaId): ?ConfiguracionCorreo
    {
        return $this->consulta()->where('empresa_id', $empresaId)->first();
    }
}
