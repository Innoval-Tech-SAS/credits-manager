<?php

namespace App\Contabilidad\Requests;

use App\Contabilidad\Enums\NaturalezaDocumento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsultarEdadesCarteraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'naturaleza' => ['required', Rule::enum(NaturalezaDocumento::class)],
            'tercero_id' => ['nullable', Rule::exists('terceros', 'id')->where('empresa_id', $this->user()->empresa_id)],
            'fecha_corte' => ['nullable', 'date'],
        ];
    }
}
