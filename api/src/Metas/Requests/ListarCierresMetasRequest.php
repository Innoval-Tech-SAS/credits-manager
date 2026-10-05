<?php

namespace App\Metas\Requests;

use App\Metas\Constants\MetasConstants;
use App\Metas\Enums\TipoMeta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarCierresMetasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'periodo' => ['nullable', 'string', 'regex:'.MetasConstants::PATRON_PERIODO],
            'tipo' => ['nullable', Rule::enum(TipoMeta::class)],
        ];
    }
}
