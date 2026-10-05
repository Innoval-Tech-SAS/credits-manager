<?php

namespace App\Metas\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Core\Dinero\DineroCast;
use App\Metas\Enums\TipoMeta;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['periodo', 'tipo', 'valor_objetivo', 'creado_por'])]
class Meta extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'metas';

    protected function casts(): array
    {
        return [
            'tipo' => TipoMeta::class,
            'valor_objetivo' => DineroCast::class,
        ];
    }

    public function asesores(): HasMany
    {
        return $this->hasMany(MetaAsesor::class);
    }
}
