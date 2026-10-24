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

    /** Si un documento tiene al menos una aplicación vigente (de cualquier módulo), ya no se puede anular ni reversar su causación. */
    public function existeVigentePara(int $documentoTerceroId): bool
    {
        return $this->consulta()
            ->where('documento_tercero_id', $documentoTerceroId)
            ->whereNull('reversado_en')
            ->exists();
    }

    /** Todas las aplicaciones de un documento (vigentes y reversadas), en el orden en que ocurrieron — para armar el árbol de trazabilidad. */
    public function deDocumento(int $documentoTerceroId): Collection
    {
        return $this->consulta()
            ->where('documento_tercero_id', $documentoTerceroId)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();
    }
}
