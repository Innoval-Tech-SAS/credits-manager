<?php

namespace App\Core\Consecutivos\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Core\Consecutivos\Enums\TipoDocumentoConsecutivo;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['tipo_documento', 'prefijo', 'ultimo_numero'])]
class ConsecutivoDocumento extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'consecutivos_documento';

    protected function casts(): array
    {
        return [
            'tipo_documento' => TipoDocumentoConsecutivo::class,
            'ultimo_numero' => 'integer',
        ];
    }
}
