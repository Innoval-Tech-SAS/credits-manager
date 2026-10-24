<?php

namespace App\Monitoreo\Requests;

use App\Monitoreo\Constants\TareaProgramadaConstants;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ActualizarTareaProgramadaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hora' => ['required', 'date_format:H:i'],
            'activa' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validador): void
    {
        $validador->after(function (Validator $validador) {
            $esNoPausable = $this->route('clave') === TareaProgramadaConstants::CLAVE_NO_PAUSABLE;

            if ($esNoPausable && $this->boolean('activa') === false) {
                $validador->errors()->add('activa', __('validation.custom.tareas_programadas.no_pausable'));
            }
        });
    }
}
