<?php

namespace App\Clientes\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Core\Concerns\RestringidoPorAsesor;
use App\Creditos\Models\Credito;
use App\Documentos\Models\ArchivoAdjunto;
use App\Empleadores\Models\Empleador;
use App\Terceros\Models\Tercero;
use App\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['tercero_id', 'empleador_id', 'asesor_id', 'activo', 'creado_por'])]
class Cliente extends BaseModel
{
    use PerteneceAEmpresa;
    use RestringidoPorAsesor;

    protected $table = 'clientes';

    protected static function columnaDeClienteParaAlcance(): ?string
    {
        return null;
    }

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

    public function asesor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'asesor_id');
    }

    public function empleador(): BelongsTo
    {
        return $this->belongsTo(Empleador::class);
    }

    public function creditos(): HasMany
    {
        return $this->hasMany(Credito::class);
    }

    public function documentos(): MorphMany
    {
        return $this->morphMany(ArchivoAdjunto::class, 'adjuntable');
    }

    public function historialEmpleadores(): HasMany
    {
        return $this->hasMany(ClienteEmpleadorHistorial::class);
    }
}
