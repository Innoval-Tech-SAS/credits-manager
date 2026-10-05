<?php

namespace App\Facturacion\Models;

use App\Clientes\Models\Cliente;
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
#[Fillable(['tipo', 'estado', 'consecutivo', 'numero', 'fecha', 'cliente_id', 'credito_id', 'documento_origen_id', 'motivo', 'emitido_en', 'emitido_por'])]
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
            'emitido_en' => 'datetime',
        ];
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

    public function emisor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'emitido_por');
    }
}
