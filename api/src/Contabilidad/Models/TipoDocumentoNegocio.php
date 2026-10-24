<?php

namespace App\Contabilidad\Models;

use App\Contabilidad\Enums\CodigoDocumentoNegocio;
use App\Contabilidad\Enums\NaturalezaNegocio;
use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['empresa_id', 'codigo', 'nombre', 'naturaleza', 'crea_saldo', 'cruza_saldo', 'activo'])]
class TipoDocumentoNegocio extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'tipos_documento_negocio';

    protected function casts(): array
    {
        return [
            'codigo' => CodigoDocumentoNegocio::class,
            'naturaleza' => NaturalezaNegocio::class,
            'crea_saldo' => 'boolean',
            'cruza_saldo' => 'boolean',
            'activo' => 'boolean',
        ];
    }
}
