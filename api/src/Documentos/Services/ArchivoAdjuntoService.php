<?php

namespace App\Documentos\Services;

use App\Documentos\Models\ArchivoAdjunto;
use App\Documentos\Repositories\ArchivoAdjuntoRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

class ArchivoAdjuntoService
{
    public function __construct(protected ArchivoAdjuntoRepository $archivos) {}

    public function subir(Model $adjuntable, UploadedFile $archivo): ArchivoAdjunto
    {
        $ruta = $archivo->store('documentos', 'public');

        return $this->archivos->crear([
            'adjuntable_type' => $adjuntable->getMorphClass(),
            'adjuntable_id' => $adjuntable->getKey(),
            'nombre_original' => $archivo->getClientOriginalName(),
            'ruta' => 'storage/'.$ruta,
            'mime_type' => $archivo->getClientMimeType(),
            'tamano' => $archivo->getSize(),
            'subido_por' => Auth::id(),
        ]);
    }

    public function listarPara(Model $adjuntable): Collection
    {
        return $this->archivos->paraAdjuntable($adjuntable);
    }
}
