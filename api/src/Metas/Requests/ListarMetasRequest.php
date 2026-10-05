<?php

namespace App\Metas\Requests;

use App\Metas\Constants\MetasConstants;
use Illuminate\Foundation\Http\FormRequest;

class ListarMetasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'periodo' => ['nullable', 'string', 'regex:'.MetasConstants::PATRON_PERIODO],
        ];
    }
}
