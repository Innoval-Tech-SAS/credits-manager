<?php

namespace App\Gastos\Models;

use App\Contabilidad\Models\CentroCosto;
use App\Contabilidad\Models\Comprobante;
use App\Contabilidad\Models\DocumentoTercero;
use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Core\Dinero\DineroCast;
use App\Gastos\Enums\EstadoGasto;
use App\Geografia\Models\Municipio;
use App\Terceros\Models\Tercero;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tipo_gasto_id',
    'tercero_id',
    'municipio_id',
    'centro_costo_id',
    'fecha',
    'numero_documento',
    'concepto',
    'valor_bruto',
    'valor_iva',
    'valor_retefuente',
    'valor_reteica',
    'valor_reteiva',
    'valor_neto',
    'parametrizacion_tipo_gasto_id',
    'comprobante_id',
    'documento_tercero_id',
    'estado',
    'ajuste_manual',
    'justificacion_ajuste',
    'creado_por',
])]
class Gasto extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'gastos';

    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'valor_bruto' => DineroCast::class,
            'valor_iva' => DineroCast::class,
            'valor_retefuente' => DineroCast::class,
            'valor_reteica' => DineroCast::class,
            'valor_reteiva' => DineroCast::class,
            'valor_neto' => DineroCast::class,
            'estado' => EstadoGasto::class,
            'ajuste_manual' => 'boolean',
            'anulado_en' => 'datetime',
        ];
    }

    public function tipoGasto(): BelongsTo
    {
        return $this->belongsTo(TipoGasto::class);
    }

    public function tercero(): BelongsTo
    {
        return $this->belongsTo(Tercero::class);
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    public function centroCosto(): BelongsTo
    {
        return $this->belongsTo(CentroCosto::class);
    }

    public function parametrizacion(): BelongsTo
    {
        return $this->belongsTo(ParametrizacionTipoGasto::class, 'parametrizacion_tipo_gasto_id');
    }

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(Comprobante::class);
    }

    public function documentoTercero(): BelongsTo
    {
        return $this->belongsTo(DocumentoTercero::class);
    }

    public function reglasFiscalesAplicadas(): HasMany
    {
        return $this->hasMany(GastoReglaFiscalAplicada::class);
    }
}
