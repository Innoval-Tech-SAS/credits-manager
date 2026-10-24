<?php

namespace App\Documentos\Repositories;

use App\Core\Base\BaseRepository;
use App\Documentos\Models\ArchivoAdjunto;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ArchivoAdjuntoRepository extends BaseRepository
{
    public function __construct(ArchivoAdjunto $archivoAdjunto)
    {
        parent::__construct($archivoAdjunto);
    }

    public function paraAdjuntable(Model $adjuntable): Collection
    {
        return $this->consulta()
            ->where('adjuntable_type', $adjuntable->getMorphClass())
            ->where('adjuntable_id', $adjuntable->getKey())
            ->orderByDesc('creado_en')
            ->get();
    }
}
