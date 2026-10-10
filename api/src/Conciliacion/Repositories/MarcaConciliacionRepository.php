<?php

namespace App\Conciliacion\Repositories;

use App\Conciliacion\Enums\TipoMarcaConciliacion;
use App\Conciliacion\Models\MarcaConciliacion;
use App\Core\Base\BaseRepository;
use Illuminate\Database\Query\Builder;

class MarcaConciliacionRepository extends BaseRepository
{
    public function __construct(MarcaConciliacion $marcaConciliacion)
    {
        parent::__construct($marcaConciliacion);
    }

    /** La marca `conciliado` vigente de un asiento (sin una `reversa` que la deshaga), si la tiene. */
    public function vigenteDeAsiento(int $asientoContableId): ?MarcaConciliacion
    {
        return $this->consulta()
            ->where('asiento_contable_id', $asientoContableId)
            ->where('tipo', TipoMarcaConciliacion::Conciliado->value)
            ->whereNotExists(fn (Builder $reversa) => $reversa
                ->selectRaw('1')
                ->from('marcas_conciliacion as r')
                ->whereColumn('r.marca_origen_id', 'marcas_conciliacion.id'))
            ->first();
    }

    public function existeReversaDe(int $marcaId): bool
    {
        return $this->consulta()->where('marca_origen_id', $marcaId)->exists();
    }
}
