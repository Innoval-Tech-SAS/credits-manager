<?php

namespace App\Terceros\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Extensión de Tercero para la contraparte que no es cliente ni proveedor. */
#[Fillable(['tercero_id', 'activo'])]
class OtroTercero extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'otro_terceros';

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function tercero(): BelongsTo
    {
        return $this->belongsTo(Tercero::class);
    }
}
