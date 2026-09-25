<?php

namespace App\Terceros\Rules;

use App\Terceros\Services\DigitoVerificacionNit;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class DigitoVerificacionValido implements DataAwareRule, ValidationRule
{
    protected array $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $nit = (string) ($this->data['numero_documento'] ?? '');

        if ($nit === '' || ! ctype_digit($nit)) {
            return;
        }

        if ((string) $value !== (string) DigitoVerificacionNit::calcular($nit)) {
            $fail('validation.custom.tercero.digito_verificacion.invalido')->translate();
        }
    }
}
