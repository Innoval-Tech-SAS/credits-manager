<?php

namespace App\Importacion\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Importacion\Enums\FaseImportacion;
use App\Importacion\Enums\ImportacionSeveridad;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['empresa_id', 'importacion_id', 'fila', 'columna', 'severidad', 'fase', 'motivo'])]
class ImportacionError extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'importacion_errores';

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(Importacion::class);
    }

    protected function casts(): array
    {
        return [
            'empresa_id' => 'integer',
            'importacion_id' => 'integer',
            'fila' => 'integer',
            'severidad' => ImportacionSeveridad::class,
            'fase' => FaseImportacion::class,
        ];
    }
}
