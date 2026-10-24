<?php

namespace App\Facturacion\Requests;

use App\Facturacion\Enums\TipoConceptoFacturacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarBorradorFacturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'fecha_vencimiento' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.tipo_concepto' => ['required', Rule::enum(TipoConceptoFacturacion::class)],
            'lineas.*.cuota_id' => [
                'nullable', 'integer',
                Rule::exists('cuotas', 'id')->where('credito_id', $this->route('documento')->credito_id),
            ],
            'lineas.*.concepto' => ['required', 'string', 'max:60'],
            'lineas.*.descripcion' => ['required', 'string', 'max:255'],
            'lineas.*.valor' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
