<?php

namespace App\Importacion\Models;

use App\Contabilidad\Models\PlanCuenta;
use App\Contabilidad\Models\TipoComprobante;
use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Empresas\Models\Empresa;
use App\Importacion\Enums\EstadoImportacion;
use App\Importacion\Enums\TipoImportacion;
use App\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'empresa_id', 'usuario_id', 'tipo', 'estado', 'archivo_original', 'archivo_ruta',
    'preferencia_crear_empleadores', 'preferencia_generar_asiento', 'cuenta_contrapartida_id', 'tipo_comprobante_id',
    'decision_crear_empleadores', 'decision_generar_asiento', 'comprobante_id',
    'total_filas', 'filas_aceptadas', 'filas_rechazadas', 'filas_pendientes', 'empleadores_pendientes',
    'error', 'iniciada_en', 'validada_en', 'completada_en',
])]
#[Hidden(['archivo_ruta'])]
class Importacion extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'importaciones';

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function cuentaContrapartida(): BelongsTo
    {
        return $this->belongsTo(PlanCuenta::class, 'cuenta_contrapartida_id');
    }

    public function tipoComprobante(): BelongsTo
    {
        return $this->belongsTo(TipoComprobante::class);
    }

    public function errores(): HasMany
    {
        return $this->hasMany(ImportacionError::class);
    }

    protected function casts(): array
    {
        return [
            'empresa_id' => 'integer',
            'usuario_id' => 'integer',
            'tipo' => TipoImportacion::class,
            'estado' => EstadoImportacion::class,
            'preferencia_crear_empleadores' => 'boolean',
            'preferencia_generar_asiento' => 'boolean',
            'decision_crear_empleadores' => 'boolean',
            'decision_generar_asiento' => 'boolean',
            'total_filas' => 'integer',
            'filas_aceptadas' => 'integer',
            'filas_rechazadas' => 'integer',
            'filas_pendientes' => 'integer',
            'empleadores_pendientes' => 'integer',
            'iniciada_en' => 'datetime',
            'validada_en' => 'datetime',
            'completada_en' => 'datetime',
        ];
    }
}
