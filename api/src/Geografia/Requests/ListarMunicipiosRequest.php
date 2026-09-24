<?php

namespace App\Geografia\Requests;

use App\Geografia\Constants\GeografiaConstants;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarMunicipiosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Sin estado ni texto sería el país entero: se exige al menos uno de los dos. */
    public function rules(): array
    {
        return [
            'estado_id' => ['required_without:buscar', 'nullable', 'integer', Rule::exists('estados', 'id')->whereNull('borrado_en')],
            'buscar' => ['required_without:estado_id', 'nullable', 'string', 'min:'.GeografiaConstants::MINIMO_CARACTERES_BUSQUEDA, 'max:100'],
        ];
    }
}
