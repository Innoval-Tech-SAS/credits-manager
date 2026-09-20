<?php

namespace App\Clientes\Services;

use App\Clientes\Constants\ClienteConstants;
use App\Clientes\Models\Cliente;
use App\Clientes\Repositories\ClienteEmpleadorHistorialRepository;
use App\Clientes\Repositories\ClienteRepository;
use App\Core\Dinero\Dinero;
use App\Creditos\Enums\EstadoCredito;
use App\Creditos\Repositories\CuotaRepository;
use App\Pagos\Repositories\PagoRepository;
use App\Terceros\Repositories\TerceroRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClienteService
{
    public function __construct(
        protected ClienteRepository $clientes,
        protected TerceroRepository $terceros,
        protected CuotaRepository $cuotas,
        protected PagoRepository $pagos,
        protected ClienteEmpleadorHistorialRepository $historialEmpleadores,
    ) {}

    public function listarClientes(
        ?string $busqueda,
        string $columna,
        string $direccion,
        int $porPagina,
        int $pagina,
        ?bool $activo = null,
        ?EstadoCredito $estadoCredito = null,
    ): LengthAwarePaginator {
        $columnaValida = in_array($columna, ClienteConstants::COLUMNAS_ORDENABLES, true) ? $columna : null;
        $direccionValida = $direccion === 'desc' ? 'desc' : 'asc';

        return $this->clientes->paginar(
            busqueda: $busqueda,
            columna: $columnaValida,
            direccion: $direccionValida,
            porPagina: max(1, min(100, $porPagina)),
            pagina: max(1, $pagina),
            activo: $activo,
            estadoCredito: $estadoCredito,
        );
    }

    public function crearCliente(array $datos): Cliente
    {
        return DB::transaction(function () use ($datos) {
            $tercero = $this->terceros->obtenerOCrear($this->datosTercero($datos));

            $cliente = $this->clientes->crear([
                'tercero_id' => $tercero->id,
                'empleador_id' => $datos['empleador_id'],
                'activo' => true,
                'creado_por' => Auth::id(),
            ]);

            $this->historialEmpleadores->crear([
                'cliente_id' => $cliente->id,
                'empleador_id' => $datos['empleador_id'],
                'fecha_inicio' => now()->toDateString(),
            ]);

            return $cliente->setAttribute('tercero_reutilizado', ! $tercero->wasRecentlyCreated);
        });
    }

    public function editarCliente(Cliente $cliente, array $datos): Cliente
    {
        return DB::transaction(function () use ($cliente, $datos) {
            $this->terceros->actualizar($cliente->tercero, $this->datosTercero($datos));

            if ((int) $datos['empleador_id'] !== (int) $cliente->empleador_id) {
                $this->registrarCambioEmpleador($cliente, (int) $datos['empleador_id']);
            }

            return $this->clientes->actualizar($cliente, [
                'empleador_id' => $datos['empleador_id'],
                ...(array_key_exists('activo', $datos) ? ['activo' => $datos['activo']] : []),
            ]);
        });
    }

    /** Cierra el período de empleo vigente del cliente y abre uno nuevo con el empleador entrante. */
    private function registrarCambioEmpleador(Cliente $cliente, int $nuevoEmpleadorId): void
    {
        $hoy = now()->toDateString();

        $this->historialEmpleadores->cerrarVigente($cliente->id, $hoy);

        $this->historialEmpleadores->crear([
            'cliente_id' => $cliente->id,
            'empleador_id' => $nuevoEmpleadorId,
            'fecha_inicio' => $hoy,
        ]);
    }

    private function datosTercero(array $datos): array
    {
        return [
            'nombre' => $datos['nombre'],
            'tipo_documento' => $datos['tipo_documento'],
            'numero_documento' => $datos['numero_documento'],
            'direccion' => $datos['direccion'],
            'barrio' => $datos['barrio'] ?? null,
            'ciudad' => $datos['ciudad'],
            'celular' => $datos['celular'],
            'telefono_fijo' => $datos['telefono_fijo'] ?? null,
            'correo' => $datos['correo'],
            'regimen_tributario' => $datos['regimen_tributario'] ?? null,
            'tipo_persona' => $datos['tipo_persona'] ?? null,
            'autorretenedor' => $datos['autorretenedor'] ?? false,
            'responsable_iva' => $datos['responsable_iva'] ?? false,
            'actividad_economica_id' => $datos['actividad_economica_id'] ?? null,
        ];
    }

    public function eliminar(Cliente $cliente): bool
    {
        if ($cliente->creditos()->whereNotIn('estado', [EstadoCredito::Pagado, EstadoCredito::Cancelado])->exists()) {
            throw ValidationException::withMessages([
                'cliente' => __('validation.custom.cliente.tiene_creditos_activos'),
            ]);
        }

        return $this->clientes->eliminar($cliente);
    }

    public function resumenCliente(Cliente $cliente): array
    {
        $creditos = $cliente->creditos()->orderByDesc('creado_en')->get();
        $resumenCuotas = $this->cuotas->resumenPorCreditos($creditos->pluck('id')->all());

        $saldoConsolidado = Dinero::sumarVarios(...array_map(
            fn (array $fila) => Dinero::desdeCentavos($fila['saldo']),
            $resumenCuotas,
        ));

        return [
            'creditos' => $creditos,
            'saldo_consolidado' => $saldoConsolidado,
            'ultimos_pagos' => $this->pagos->porCliente($cliente->id),
            'documentos' => $cliente->documentos()->orderByDesc('creado_en')->get(),
            'historial_empleadores' => $cliente->historialEmpleadores()->with('empleador')->orderByDesc('fecha_inicio')->get(),
        ];
    }
}
