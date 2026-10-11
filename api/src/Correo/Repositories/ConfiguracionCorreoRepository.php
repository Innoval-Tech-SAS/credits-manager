<?php

namespace App\Correo\Repositories;

use App\Core\Base\BaseRepository;
use App\Correo\Enums\TipoCorreo;
use App\Correo\Models\ConfiguracionCorreo;

class ConfiguracionCorreoRepository extends BaseRepository
{
    public function __construct(ConfiguracionCorreo $configuracion)
    {
        parent::__construct($configuracion);
    }

    /** Explícito por empresa: los jobs y comandos no tienen sesión, así que el scope no filtra por ellos. */
    public function deEmpresaYTipo(int $empresaId, TipoCorreo $tipo): ?ConfiguracionCorreo
    {
        return $this->consulta()->where('empresa_id', $empresaId)->where('tipo', $tipo)->first();
    }
}
