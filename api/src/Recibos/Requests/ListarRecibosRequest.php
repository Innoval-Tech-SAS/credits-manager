<?php

namespace App\Recibos\Requests;

use App\Recibos\Enums\EstadoRecibo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarRecibosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tercero_id' => ['nullable', 'integer', Rule::exists('terceros', 'id')->where('empresa_id', $this->user()->empresa_id)],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'estado' => ['nullable', Rule::enum(EstadoRecibo::class)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
