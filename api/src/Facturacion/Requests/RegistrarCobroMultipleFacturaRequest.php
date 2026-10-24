<?php

namespace App\Facturacion\Requests;

use App\Facturacion\Enums\MedioCobroFacturacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarCobroMultipleFacturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;

        return [
            'tercero_id' => ['required', Rule::exists('terceros', 'id')->where('empresa_id', $empresaId)],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'medio' => ['required', Rule::enum(MedioCobroFacturacion::class)],
            'referencia' => ['nullable', 'string', 'max:60'],
            'observacion' => ['nullable', 'string', 'max:500'],
            'aplicaciones' => ['required', 'array', 'min:2'],
            'aplicaciones.*.documento_facturacion_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('documentos_facturacion', 'id')->where('empresa_id', $empresaId),
            ],
            'aplicaciones.*.valor' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
