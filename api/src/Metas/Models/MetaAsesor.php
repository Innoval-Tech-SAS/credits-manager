<?php

namespace App\Metas\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Core\Dinero\DineroCast;
use App\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['empresa_id', 'meta_id', 'usuario_id', 'valor_objetivo'])]
class MetaAsesor extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'metas_asesor';

    protected function casts(): array
    {
        return [
            'valor_objetivo' => DineroCast::class,
        ];
    }

    public function meta(): BelongsTo
    {
        return $this->belongsTo(Meta::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
