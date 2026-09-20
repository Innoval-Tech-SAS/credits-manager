<?php

namespace App\Contabilidad\Repositories;

use App\Contabilidad\Enums\EstadoDocumentoTercero;
use App\Contabilidad\Enums\NaturalezaDocumento;
use App\Contabilidad\Models\DocumentoTercero;
use App\Core\Base\BaseRepository;
use App\Terceros\Models\Tercero;
use Illuminate\Database\Eloquent\Collection;

class DocumentoTerceroRepository extends BaseRepository
{
    public function __construct(DocumentoTercero $documentoTercero)
    {
        parent::__construct($documentoTercero);
    }

    public function pendientesPorTercero(Tercero $tercero, NaturalezaDocumento $naturaleza): Collection
    {
        return $this->consulta()
            ->where('tercero_id', $tercero->id)
            ->where('naturaleza', $naturaleza)
            ->where('estado', EstadoDocumentoTercero::Pendiente)
            ->orderBy('fecha')
            ->get();
    }

    /**
     * Bloquea (`lockForUpdate`) y devuelve los documentos pendientes de `$tercero`
     * cuyo id esté en `$ids`. Se usa dentro de una transacción para validar que la
     * selección del usuario sigue siendo válida (mismo tercero, misma naturaleza,
     * aún pendiente) justo antes de aplicarles un valor.
     */
    public function bloquearPendientesPorIds(array $ids, int $terceroId, NaturalezaDocumento $naturaleza): Collection
    {
        return $this->consulta()
            ->whereIn('id', $ids)
            ->where('tercero_id', $terceroId)
            ->where('naturaleza', $naturaleza)
            ->where('estado', EstadoDocumentoTercero::Pendiente)
            ->lockForUpdate()
            ->get();
    }
}
