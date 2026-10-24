<?php

namespace App\Contabilidad\Requests;

use App\Contabilidad\Enums\NaturalezaDocumento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsultarCarteraTerceroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;

        return [
            'naturaleza' => ['required', Rule::enum(NaturalezaDocumento::class)],
            'tercero_id' => ['nullable', Rule::exists('terceros', 'id')->where('empresa_id', $empresaId)],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'estado' => ['nullable', Rule::in(['pendiente', 'abonado_parcial', 'cancelado', 'vencido'])],
            'plan_cuenta_id' => ['nullable', Rule::exists('plan_cuentas', 'id')->where('empresa_id', $empresaId)],
            'fecha_corte' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
