<?php

namespace App\Documentos\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Services\EmisorAuditoria;
use App\Documentos\Models\ArchivoAdjunto;
use App\Documentos\Repositories\ArchivoAdjuntoRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ArchivoAdjuntoService
{
    public function __construct(
        protected ArchivoAdjuntoRepository $archivos,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function subir(Model $adjuntable, UploadedFile $archivo): ArchivoAdjunto
    {
        $ruta = $archivo->store('documentos', 'public');

        $adjunto = $this->archivos->crear([
            'adjuntable_type' => $adjuntable->getMorphClass(),
            'adjuntable_id' => $adjuntable->getKey(),
            'nombre_original' => $archivo->getClientOriginalName(),
            'ruta' => 'storage/'.$ruta,
            'mime_type' => $archivo->getClientMimeType(),
            'tamano' => $archivo->getSize(),
            'subido_por' => Auth::id(),
        ]);

        $this->auditoria->registrarCreacion(
            AuditoriaConstants::MODULO_DOCUMENTOS,
            "Documento {$adjunto->nombre_original} adjuntado a ".Str::lower(class_basename($adjuntable))." #{$adjuntable->getKey()}",
            $adjunto,
        );

        return $adjunto;
    }

    public function listarPara(Model $adjuntable): Collection
    {
        return $this->archivos->paraAdjuntable($adjuntable);
    }
}
