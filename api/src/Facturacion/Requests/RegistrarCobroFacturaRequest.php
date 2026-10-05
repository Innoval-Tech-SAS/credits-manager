<?php

namespace App\Facturacion\Requests;

use App\Facturacion\Enums\MedioCobroFacturacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarCobroFacturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date_format:Y-m-d'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'medio' => ['required', Rule::enum(MedioCobroFacturacion::class)],
            'referencia' => ['nullable', 'string', 'max:60'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }
}
