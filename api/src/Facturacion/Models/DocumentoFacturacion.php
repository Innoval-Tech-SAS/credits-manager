<?php

namespace App\Facturacion\Models;

use App\Clientes\Models\Cliente;
use App\Contabilidad\Models\Comprobante;
use App\Contabilidad\Models\DocumentoTercero;
use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Creditos\Models\Credito;
use App\Facturacion\Enums\EstadoDocumentoFacturacion;
use App\Facturacion\Enums\TipoDocumentoFacturacion;
use App\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Factura, nota crédito o nota débito interna. La nota apunta a su documento de origen y hereda el crédito.
 * El total no se guarda: sale de las líneas. Las invariantes de número, origen y valor están en la base
 * (ver la migración de documentos); los montos acumulados los controla DocumentoFacturacionService.
 */
#[Fillable(['tipo', 'estado', 'consecutivo', 'numero', 'fecha', 'fecha_vencimiento', 'cliente_id', 'credito_id', 'documento_origen_id', 'motivo_codigo', 'motivo', 'solicitado_por', 'emitido_en', 'emitido_por', 'comprobante_id', 'documento_tercero_id'])]
class DocumentoFacturacion extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'documentos_facturacion';

    protected function casts(): array
    {
        return [
            'tipo' => TipoDocumentoFacturacion::class,
            'estado' => EstadoDocumentoFacturacion::class,
            'consecutivo' => 'integer',
            'fecha' => 'date:Y-m-d',
            'fecha_vencimiento' => 'date:Y-m-d',
            'emitido_en' => 'datetime',
        ];
    }

    public function documentoTercero(): BelongsTo
    {
        return $this->belongsTo(DocumentoTercero::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function credito(): BelongsTo
    {
        return $this->belongsTo(Credito::class);
    }

    public function origen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'documento_origen_id');
    }

    public function notas(): HasMany
    {
        return $this->hasMany(self::class, 'documento_origen_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(LineaDocumentoFacturacion::class, 'documento_id')->orderBy('orden');
    }

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(Comprobante::class);
    }

    public function cobros(): HasMany
    {
        return $this->hasMany(CobroFacturacion::class, 'documento_id');
    }

    public function emisor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'emitido_por');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'solicitado_por');
    }
}
