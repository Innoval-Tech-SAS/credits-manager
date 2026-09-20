<?php

namespace App\Documentos\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['adjuntable_type', 'adjuntable_id', 'nombre_original', 'ruta', 'mime_type', 'tamano', 'subido_por'])]
class ArchivoAdjunto extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'archivos_adjuntos';

    protected function casts(): array
    {
        return [
            'tamano' => 'integer',
        ];
    }

    public function adjuntable(): MorphTo
    {
        return $this->morphTo();
    }
}
