<?php

namespace App\Contabilidad\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['codigo', 'nombre', 'activo', 'consecutivo_actual'])]
class TipoComprobante extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'tipos_comprobante';

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'consecutivo_actual' => 'integer',
        ];
    }

    public function comprobantes(): HasMany
    {
        return $this->hasMany(Comprobante::class);
    }
}
