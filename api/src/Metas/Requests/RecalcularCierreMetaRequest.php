<?php

namespace App\Metas\Requests;

use App\Metas\Constants\MetasConstants;
use App\Metas\Enums\TipoMeta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecalcularCierreMetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'periodo' => ['required', 'string', 'regex:'.MetasConstants::PATRON_PERIODO],
            'tipo' => ['required', Rule::enum(TipoMeta::class)],
            'motivo' => ['required', 'string', 'min:10', 'max:255'],
        ];
    }
}
