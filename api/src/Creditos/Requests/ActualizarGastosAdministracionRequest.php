<?php

namespace App\Creditos\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarGastosAdministracionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gastos_administracion_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'motivo' => ['required', 'string', 'max:500'],
        ];
    }
}
