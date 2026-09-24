<?php

namespace App\Empresas\Models;

use App\Core\Base\BaseModel;
use App\Geografia\Models\Municipio;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['nombre_empresa', 'nit', 'direccion', 'municipio_id', 'telefono', 'correo', 'logo_path'])]
class Empresa extends BaseModel
{
    protected $table = 'empresas';

    protected function casts(): array
    {
        return [
            'municipio_id' => 'integer',
        ];
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }
}
