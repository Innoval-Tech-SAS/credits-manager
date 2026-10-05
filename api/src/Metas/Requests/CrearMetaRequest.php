<?php

namespace App\Metas\Requests;

use App\Metas\Constants\MetasConstants;
use App\Metas\Enums\TipoMeta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearMetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'periodo' => ['required', 'string', 'regex:'.MetasConstants::PATRON_PERIODO],
            'tipo' => [
                'required',
                Rule::enum(TipoMeta::class),
                Rule::unique('metas', 'tipo')->where(fn ($consulta) => $consulta
                    ->where('empresa_id', $this->user()->empresa_id)
                    ->where('periodo', $this->input('periodo'))
                    ->whereNull('borrado_en')),
            ],
            'valor_objetivo' => ['required', 'numeric', 'min:0.01', 'max:999999999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.unique' => __('validation.custom.metas.periodo_tipo_repetido'),
        ];
    }
}
