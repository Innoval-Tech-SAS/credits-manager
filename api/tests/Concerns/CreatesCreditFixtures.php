<?php

namespace Tests\Concerns;

use App\Clientes\Models\Cliente;
use App\Creditos\Enums\EstadoCredito;
use App\Creditos\Enums\EstadoCuota;
use App\Creditos\Enums\TipoCredito;
use App\Creditos\Enums\TipoPago;
use App\Creditos\Models\Credito;
use App\Creditos\Models\Cuota;
use App\Empleadores\Models\Empleador;
use App\Terceros\Enums\TipoPersona;
use App\Terceros\Models\Tercero;

/**
 * Helpers de fixtures para tests de Créditos/Pagos/Cartera — evita repetir la
 * creación de cliente/crédito/cuota (con sus campos requeridos) en cada test.
 * Requieren un usuario autenticado (ver ActsAsAdmin): `empresa_id` se rellena
 * solo, desde ese usuario, vía PerteneceAEmpresa.
 */
trait CreatesCreditFixtures
{
    use ArmaDatosDeTercero;

    protected function crearEmpleador(array $overrides = []): Empleador
    {
        static $contador = 0;
        $contador++;

        return Empleador::create(array_merge([
            'nombre' => "Empleador de prueba {$contador}",
        ], $overrides));
    }

    protected function crearCliente(array $overrides = []): Cliente
    {
        static $contador = 0;
        $contador++;

        $camposTercero = [
            'nombre', 'tipo_documento', 'numero_documento',
            'direccion', 'barrio', 'ciudad', 'celular', 'telefono_fijo', 'correo',
        ];
        $datosTercero = array_intersect_key($overrides, array_flip($camposTercero));
        $datosCliente = array_diff_key($overrides, array_flip($camposTercero));

        $tercero = Tercero::create(array_merge(
            $this->datosDePersona(
                TipoPersona::Natural,
                $datosTercero['nombre'] ?? "Cliente de prueba {$contador}",
                $datosTercero['numero_documento'] ?? "100000{$contador}",
            ),
            [
                'direccion' => 'Calle falsa 123',
                'ciudad' => 'Bogotá',
                'celular' => '3000000000',
                'correo' => "cliente{$contador}@example.com",
            ],
            array_diff_key($datosTercero, array_flip(['nombre', 'numero_documento'])),
        ));

        return Cliente::create(array_merge([
            'tercero_id' => $tercero->id,
            'empleador_id' => $this->crearEmpleador()->id,
        ], $datosCliente));
    }

    protected function crearCredito(Cliente $cliente, array $overrides = []): Credito
    {
        return Credito::create(array_merge([
            'cliente_id' => $cliente->id,
            'tipo_credito' => TipoCredito::Nuevo,
            'valor_prestamo' => 900,
            'tasa_interes' => 0,
            'plazo' => 3,
            'tipo_pago' => TipoPago::Mensual,
            'valor_cuota' => 300,
            'valor_total' => 900,
            'fecha_desembolso' => '2026-01-01',
            'estado' => EstadoCredito::Desembolsado,
        ], $overrides));
    }

    protected function crearCuota(Credito $credito, array $overrides = []): Cuota
    {
        return Cuota::create(array_merge([
            'credito_id' => $credito->id,
            'numero_cuota' => 1,
            'fecha_vencimiento' => '2026-02-01',
            'valor' => 300,
            'valor_interes' => 0,
            'valor_capital' => 300,
            'saldo_capital' => 0,
            'valor_pagado' => 0,
            'estado' => EstadoCuota::Pendiente,
        ], $overrides));
    }
}
