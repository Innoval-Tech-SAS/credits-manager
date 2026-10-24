<?php

namespace App\Contabilidad\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RechazarSolicitudReaperturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo_resolucion' => ['required', 'string', 'max:500'],
        ];
    }
}
