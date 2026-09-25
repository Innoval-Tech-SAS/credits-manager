<?php

namespace App\Clientes\Requests;

use App\Clientes\Reglas\ReglasAsesor;
use Illuminate\Foundation\Http\FormRequest;

class ReasignarAsesorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'asesor_id' => ['required', 'integer', ReglasAsesor::asignable($this->user()->empresa_id)],
            'motivo' => ['required', 'string', 'max:255'],
        ];
    }
}
