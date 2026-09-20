<?php

namespace App\Clientes\Requests;

use App\Terceros\Enums\TipoDocumentoTercero;
use App\Terceros\Enums\TipoPersona;
use App\Terceros\Repositories\TerceroRepository;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearClienteRequest extends FormRequest
{
    public function __construct(protected TerceroRepository $terceros)
    {
        parent::__construct();
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150'],
            'tipo_documento' => ['required', Rule::enum(TipoDocumentoTercero::class)],
            'numero_documento' => ['required', 'string', 'max:30'],
            'direccion' => ['required', 'string', 'max:200'],
            'barrio' => ['nullable', 'string', 'max:100'],
            'ciudad' => ['required', 'string', 'max:100'],
            'celular' => ['required', 'string', 'max:30'],
            'telefono_fijo' => ['nullable', 'string', 'max:30'],
            'correo' => ['required', 'email', 'max:150'],
            'empleador_id' => [
                'required', 'integer',
                Rule::exists('empleadores', 'id')->where('empresa_id', $this->user()->empresa_id),
            ],
            'regimen_tributario' => ['nullable', 'string', 'max:60'],
            'tipo_persona' => ['nullable', Rule::enum(TipoPersona::class)],
            'autorretenedor' => ['sometimes', 'boolean'],
            'responsable_iva' => ['sometimes', 'boolean'],
            'actividad_economica_id' => [
                'nullable',
                Rule::exists('actividades_economicas', 'id')->where('empresa_id', $this->user()->empresa_id),
            ],
        ];
    }

    public function withValidator(ValidatorContract $validador): void
    {
        $validador->after(function (ValidatorContract $validador) {
            if (! $this->filled('tipo_documento') || ! $this->filled('numero_documento')) {
                return;
            }

            $tercero = $this->terceros->buscarPorDocumento($this->input('tipo_documento'), $this->input('numero_documento'));

            if ($tercero && $tercero->cliente) {
                $validador->errors()->add('numero_documento', __('validation.custom.tercero.numero_documento.ya_es_cliente'));
            }
        });
    }
}
