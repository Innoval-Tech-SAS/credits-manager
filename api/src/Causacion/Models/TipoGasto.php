<?php

namespace App\Causacion\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['codigo', 'nombre', 'requiere_tercero', 'aplica_iva', 'activo'])]
class TipoGasto extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'tipos_gasto';

    protected function casts(): array
    {
        return [
            'requiere_tercero' => 'boolean',
            'aplica_iva' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function parametrizaciones(): HasMany
    {
        return $this->hasMany(ParametrizacionTipoGasto::class);
    }
}
