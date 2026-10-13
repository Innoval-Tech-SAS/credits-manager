<?php

namespace App\ObligacionesTributarias\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EditarObligacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;

        return [
            'fecha_limite' => ['required', 'date'],
            'responsable_id' => ['nullable', Rule::exists('usuarios', 'id')->where('empresa_id', $empresaId)->whereNull('borrado_en')],
            'observaciones' => ['nullable', 'string', 'max:500'],
            'dias_alerta_previa' => ['nullable', 'integer', 'min:0', 'max:365'],
        ];
    }
}
