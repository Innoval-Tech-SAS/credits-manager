<?php

namespace App\Contabilidad\Repositories;

use App\Contabilidad\Models\AplicacionDocumento;
use App\Core\Base\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

class AplicacionDocumentoRepository extends BaseRepository
{
    public function __construct(AplicacionDocumento $aplicacionDocumento)
    {
        parent::__construct($aplicacionDocumento);
    }

    /** Aplicaciones de un documento que todavía no se han deshecho por una reversa. */
    public function vigentesDe(string $moduloAplicacion, int $aplicacionId): Collection
    {
        return $this->consulta()
            ->where('modulo_aplicacion', $moduloAplicacion)
            ->where('aplicacion_id', $aplicacionId)
            ->whereNull('reversado_en')
            ->get();
    }

    public function marcarReversadas(array $ids): void
    {
        $this->consulta()->whereIn('id', $ids)->update(['reversado_en' => now()]);
    }
}
