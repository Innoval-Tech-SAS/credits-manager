<?php

namespace App\Creditos\Requests;

use App\Creditos\Enums\EstadoCredito;
use App\Creditos\Enums\TipoCredito;
use App\Creditos\Enums\TipoPago;
use App\Creditos\Repositories\CreditoRepository;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearCreditoRequest extends FormRequest
{
    public function __construct(protected CreditoRepository $creditos)
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
            'cliente_id' => [
                'required', 'integer',
                Rule::exists('clientes', 'id')->where('empresa_id', $this->user()->empresa_id),
            ],
            'tipo_credito' => ['required', Rule::enum(TipoCredito::class)],
            'credito_origen_id' => [
                'nullable', 'required_if:tipo_credito,refinanciacion', 'integer',
                Rule::exists('creditos', 'id')->where('empresa_id', $this->user()->empresa_id),
            ],
            'valor_prestamo' => ['required', 'numeric', 'min:0.01'],
            'tasa_interes' => ['required', 'numeric', 'min:0'],
            'plazo' => ['required', 'integer', 'min:1'],
            'tipo_pago' => ['required', Rule::enum(TipoPago::class)],
            'fecha_desembolso' => ['required', 'date'],
        ];
    }

    public function withValidator(ValidatorContract $validador): void
    {
        $validador->after(function (ValidatorContract $validador) {
            if ($this->input('tipo_credito') !== TipoCredito::Refinanciacion->value || ! $this->filled('credito_origen_id')) {
                return;
            }

            $origen = $this->creditos->buscarPorId($this->input('credito_origen_id'));

            if ($origen && (string) $origen->cliente_id !== (string) $this->input('cliente_id')) {
                $validador->errors()->add('credito_origen_id', __('validation.custom.credito_origen_id.pertenece_a_otro_cliente'));
            }

            if ($origen && $origen->estado !== EstadoCredito::Desembolsado) {
                $validador->errors()->add('credito_origen_id', __('validation.custom.credito_origen_id.no_desembolsado'));
            }

            if ($origen && $this->creditos->existeRefinanciacionEnCurso($origen->id)) {
                $validador->errors()->add('credito_origen_id', __('validation.custom.credito_origen_id.en_curso'));
            }
        });
    }
}
