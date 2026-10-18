<?php

namespace App\Gastos\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Corrige cuentas, tarifas y retenciones de una versión ya guardada. El tipo de gasto y la vigencia no se editan:
 * cambiar desde cuándo rige una configuración es crear una versión nueva (`CrearParametrizacionTipoGastoRequest`).
 */
class EditarParametrizacionTipoGastoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return CrearParametrizacionTipoGastoRequest::reglasContables($this->user()->empresa_id);
    }
}
