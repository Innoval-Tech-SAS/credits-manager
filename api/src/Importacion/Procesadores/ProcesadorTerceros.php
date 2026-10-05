<?php

namespace App\Importacion\Procesadores;

use App\Clientes\Constants\ClienteConstants;
use App\Clientes\Repositories\ClienteRepository;
use App\Clientes\Services\AsignacionAsesorService;
use App\Importacion\Data\ContextoEjecucion;
use App\Importacion\Data\FilaRechazada;
use App\Proveedores\Repositories\ProveedorRepository;
use App\Terceros\Enums\TipoPersona;
use App\Terceros\Enums\TipoTercero;
use App\Terceros\Reglas\ReglasPersona;
use App\Terceros\Repositories\OtroTerceroRepository;
use App\Terceros\Repositories\TerceroRepository;
use App\Terceros\Services\ConstructorDatosTercero;
use Illuminate\Support\Facades\Validator;

/** Formato anterior de terceros: un tercero con su extensión de cliente, proveedor u otro. */
final class ProcesadorTerceros implements ProcesadorFila
{
    public function __construct(
        private readonly TerceroRepository $terceros,
        private readonly ConstructorDatosTercero $constructorTercero,
        private readonly ClienteRepository $clientes,
        private readonly AsignacionAsesorService $asignaciones,
        private readonly ProveedorRepository $proveedores,
        private readonly OtroTerceroRepository $otrosTerceros,
    ) {}

    public function precargar(array $valoresLote, ContextoEjecucion $ctx): void {}

    public function procesar(array $valores, ContextoEjecucion $ctx): void
    {
        $tipoTercero = TipoTercero::tryFrom($valores['tipo'])
            ?? throw new FilaRechazada("Tipo de tercero inválido: «{$valores['tipo']}» (debe ser cliente, proveedor u otro).", 'tipo');

        $tipoPersona = TipoPersona::tryFrom($valores['tipo_persona'])
            ?? throw new FilaRechazada("Tipo de persona inválido: «{$valores['tipo_persona']}» (debe ser natural o juridica).", 'tipo_persona');

        if ($tipoTercero === TipoTercero::Cliente && $tipoPersona !== ClienteConstants::TIPO_PERSONA) {
            throw new FilaRechazada('Los clientes deben ser personas '.ClienteConstants::TIPO_PERSONA->value.'s.', 'tipo_persona');
        }

        $datos = array_map(fn (string $valor) => $valor === '' ? null : $valor, $valores);

        $validador = Validator::make($datos, match ($tipoPersona) {
            TipoPersona::Natural => ReglasPersona::natural(),
            TipoPersona::Juridica => ReglasPersona::juridica(),
        });

        if ($validador->fails()) {
            $columna = array_key_first($validador->errors()->toArray());
            throw new FilaRechazada($validador->errors()->first(), is_string($columna) ? $columna : null);
        }

        $datosTercero = $this->constructorTercero->construir($tipoPersona, $datos);
        $clave = $datosTercero['tipo_documento'].'|'.$datosTercero['numero_documento'];

        if (! $ctx->primeraVez('tercero|'.$tipoTercero->value.'|'.$clave)) {
            throw new FilaRechazada('El tercero '.$datosTercero['numero_documento'].' está repetido en el archivo.', 'numero_documento');
        }

        $existente = $this->terceros->buscarPorDocumento($datosTercero['tipo_documento'], $datosTercero['numero_documento']);

        if ($existente && $this->yaRegistradoComo($existente, $tipoTercero)) {
            throw new FilaRechazada('El tercero '.$datosTercero['numero_documento'].' ya está registrado como '.$tipoTercero->value.'.', 'numero_documento');
        }

        if (! $ctx->escribir) {
            return;
        }

        $tercero = $existente ?? $this->terceros->crear($datosTercero);

        match ($tipoTercero) {
            TipoTercero::Cliente => $this->asignaciones->registrarInicial(
                $this->clientes->crear(['tercero_id' => $tercero->id, 'asesor_id' => $ctx->usuario->id, 'activo' => true, 'creado_por' => $ctx->usuario->id]),
                'Importación de terceros',
            ),
            TipoTercero::Proveedor => $this->proveedores->crear(['tercero_id' => $tercero->id, 'activo' => true]),
            TipoTercero::Otro => $this->otrosTerceros->crear(['tercero_id' => $tercero->id, 'activo' => true]),
        };
    }

    private function yaRegistradoComo(object $tercero, TipoTercero $tipoTercero): bool
    {
        return match ($tipoTercero) {
            TipoTercero::Cliente => $tercero->cliente !== null,
            TipoTercero::Proveedor => $tercero->proveedor !== null,
            TipoTercero::Otro => $tercero->otroTercero !== null,
        };
    }
}
