<?php

namespace App\Metas\Repositories;

use App\Core\Base\BaseRepository;
use App\Metas\Data\PeriodoMeta;
use App\Metas\Enums\TipoMeta;
use App\Metas\Models\Meta;
use Illuminate\Database\Eloquent\Collection;

class MetaRepository extends BaseRepository
{
    public function __construct(Meta $meta)
    {
        parent::__construct($meta);
    }

    /** Explícito por empresa: los comandos no tienen sesión, así que el scope no filtra por ellos. */
    public function delPeriodo(int $empresaId, PeriodoMeta $periodo): Collection
    {
        return $this->consulta()
            ->where('empresa_id', $empresaId)
            ->where('periodo', $periodo->codigo())
            ->with('asesores')
            ->orderBy('tipo')
            ->get();
    }

    public function deEmpresa(int $empresaId): Collection
    {
        return $this->consulta()
            ->where('empresa_id', $empresaId)
            ->with('asesores')
            ->orderBy('periodo')
            ->orderBy('tipo')
            ->get();
    }

    public function delPeriodoYTipo(int $empresaId, PeriodoMeta $periodo, TipoMeta $tipo): ?Meta
    {
        return $this->consulta()
            ->where('empresa_id', $empresaId)
            ->where('periodo', $periodo->codigo())
            ->where('tipo', $tipo->value)
            ->first();
    }

    /** Bloquea la fila para que dos cierres o recálculos de la misma meta no se crucen. Debe usarse dentro de una transacción. */
    public function bloquear(Meta $meta): Meta
    {
        return $this->consulta()->lockForUpdate()->findOrFail($meta->id);
    }
}
