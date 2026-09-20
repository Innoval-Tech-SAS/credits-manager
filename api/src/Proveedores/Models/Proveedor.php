<?php

namespace App\Proveedores\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Terceros\Models\Tercero;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tercero_id', 'activo'])]
class Proveedor extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'proveedores';

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
