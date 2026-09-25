<?php

namespace App\Terceros\Requests\Concerns;

use App\Terceros\Repositories\TerceroRepository;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;

/** Para los Requests de creación: rechaza el documento si ya tiene la extensión (cliente, proveedor u otro) que se va a crear. */
trait ValidaDocumentoNoRegistrado
{
    public function __construct(protected TerceroRepository $terceros)
    {
        parent::__construct();
    }

    /** Tipo de documento con el que se busca: el del payload, o NIT fijo para personas jurídicas. */
    abstract protected function tipoDocumentoDelRegistro(): ?string;

    /** Relación del Tercero que ya no debe existir: `cliente`, `proveedor` u `otroTercero`. */
    abstract protected function extensionAValidar(): string;

    /** Clave en `validation.custom.tercero.numero_documento` con el mensaje del rechazo. */
    abstract protected function claveMensajeDuplicado(): string;

    public function withValidator(ValidatorContract $validador): void
    {
        $validador->after(function (ValidatorContract $validador) {
            $tipoDocumento = $this->tipoDocumentoDelRegistro();

            if (! $tipoDocumento || ! $this->filled('numero_documento')) {
                return;
            }

            $tercero = $this->terceros->buscarPorDocumento($tipoDocumento, $this->input('numero_documento'));

            if ($tercero && $tercero->{$this->extensionAValidar()}) {
                $validador->errors()->add('numero_documento', __('validation.custom.tercero.numero_documento.'.$this->claveMensajeDuplicado()));
            }
        });
    }
}
