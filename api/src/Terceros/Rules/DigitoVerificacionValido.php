<?php

namespace App\Terceros\Rules;

use App\Terceros\Services\DigitoVerificacionNit;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/** Valida que el dígito recibido corresponda al NIT de otro campo del mismo Request. */
class DigitoVerificacionValido implements DataAwareRule, ValidationRule
{
    protected array $data = [];

    public function __construct(
        private readonly string $campoNit = 'numero_documento',
        private readonly string $claveMensaje = 'tercero.digito_verificacion.invalido',
    ) {}

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $nit = (string) ($this->data[$this->campoNit] ?? '');

        if ($nit === '' || ! ctype_digit($nit)) {
            return;
        }

        if ((string) $value !== (string) DigitoVerificacionNit::calcular($nit)) {
            $fail("validation.custom.{$this->claveMensaje}")->translate();
        }
    }
}
