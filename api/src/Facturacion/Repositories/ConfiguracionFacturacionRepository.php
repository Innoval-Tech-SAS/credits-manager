<?php

namespace App\Facturacion\Repositories;

use App\Core\Base\BaseRepository;
use App\Facturacion\Models\ConfiguracionFacturacion;

class ConfiguracionFacturacionRepository extends BaseRepository
{
    public function __construct(ConfiguracionFacturacion $configuracion)
    {
        parent::__construct($configuracion);
    }

    /** @return array<string, string> clave => valor guardado */
    public function valoresDeEmpresa(int $empresaId): array
    {
        return $this->consulta()->where('empresa_id', $empresaId)->pluck('valor', 'clave')->all();
    }

    public function guardar(int $empresaId, string $clave, string $valor, int $usuarioId): void
    {
        $this->consulta()->updateOrCreate(
            ['empresa_id' => $empresaId, 'clave' => $clave],
            ['valor' => $valor, 'actualizado_por' => $usuarioId],
        );
    }
}
