<?php

namespace App\Facturacion\Requests;

use App\Facturacion\Enums\EstadoDocumentoFacturacion;
use App\Facturacion\Enums\MotivoNotaCredito;
use App\Facturacion\Enums\MotivoNotaDebito;
use App\Facturacion\Enums\TipoDocumentoFacturacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarDocumentosFacturacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['nullable', Rule::enum(TipoDocumentoFacturacion::class)],
            'estado' => ['nullable', Rule::enum(EstadoDocumentoFacturacion::class)],
            'cliente_id' => ['nullable', 'integer', Rule::exists('clientes', 'id')->where('empresa_id', $this->user()->empresa_id)],
            'tercero_id' => ['nullable', 'integer', Rule::exists('terceros', 'id')->where('empresa_id', $this->user()->empresa_id)],
            'con_saldo' => ['nullable', 'boolean'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'factura' => ['nullable', 'string', 'max:30'],
            'motivo_codigo' => ['nullable', Rule::in(array_unique([
                ...array_column(MotivoNotaCredito::cases(), 'value'),
                ...array_column(MotivoNotaDebito::cases(), 'value'),
            ]))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
