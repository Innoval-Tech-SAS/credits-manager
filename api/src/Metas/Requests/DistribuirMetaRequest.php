<?php

namespace App\Metas\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DistribuirMetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Lista vacía: quita la distribución y la meta vuelve a operar solo a nivel de empresa. */
    public function rules(): array
    {
        return [
            'asesores' => ['present', 'array', 'max:200'],
            'asesores.*.usuario_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('usuarios', 'id')
                    ->where('empresa_id', $this->user()->empresa_id)
                    ->where('activo', true)
                    ->whereNull('borrado_en'),
            ],
            'asesores.*.valor_objetivo' => ['required', 'numeric', 'min:0.01', 'max:999999999999'],
        ];
    }
}
