<?php

namespace App\Pagos\Requests;

use App\Pagos\Enums\FormaPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LiquidarCreditoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'forma_pago' => ['required', Rule::enum(FormaPago::class)],
            'observacion' => ['nullable', 'string', 'max:255'],
        ];
    }
}
