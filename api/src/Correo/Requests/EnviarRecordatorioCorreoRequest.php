<?php

namespace App\Correo\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** La plantilla se toma de la configuración guardada: el envío no acepta texto libre. */
class EnviarRecordatorioCorreoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cuota_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
