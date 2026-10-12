<?php

namespace App\Facturacion\Requests;

use App\Facturacion\Enums\EstadoDocumentoFacturacion;
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
            'con_saldo' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
