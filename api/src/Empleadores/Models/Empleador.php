<?php

namespace App\Empleadores\Models;

use App\Clientes\Models\Cliente;
use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Empresas\Models\Empresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre', 'nit', 'digito_verificacion', 'direccion', 'telefono'])]
class Empleador extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'empleadores';

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }
}
