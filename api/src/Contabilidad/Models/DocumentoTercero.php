<?php

namespace App\Contabilidad\Models;

use App\Contabilidad\Enums\EstadoDocumentoTercero;
use App\Contabilidad\Enums\NaturalezaDocumento;
use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Core\Dinero\DineroCast;
use App\Terceros\Models\Tercero;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tercero_id',
    'naturaleza',
    'tipo_documento',
    'numero_documento',
    'fecha',
    'concepto',
    'valor_original',
    'saldo',
    'estado',
    'modulo_origen',
    'origen_id',
    'documento_original_id',
])]
class DocumentoTercero extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'documentos_tercero';

    protected function casts(): array
    {
        return [
            'naturaleza' => NaturalezaDocumento::class,
            'estado' => EstadoDocumentoTercero::class,
            'fecha' => 'date:Y-m-d',
            'valor_original' => DineroCast::class,
            'saldo' => DineroCast::class,
        ];
    }

    public function tercero(): BelongsTo
    {
        return $this->belongsTo(Tercero::class);
    }

    public function documentoOriginal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'documento_original_id');
    }

    public function aplicaciones(): HasMany
    {
        return $this->hasMany(AplicacionDocumento::class);
    }
}
