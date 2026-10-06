<?php

namespace App\Clientes\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Services\EmisorAuditoria;
use App\Clientes\Constants\ClienteConstants;
use App\Clientes\Exportaciones\FuenteClientes;
use App\Clientes\Models\Cliente;
use App\Clientes\Repositories\ClienteEmpleadorHistorialRepository;
use App\Clientes\Repositories\ClienteRepository;
use App\Core\Dinero\Dinero;
use App\Creditos\Enums\EstadoCredito;
use App\Creditos\Repositories\CuotaRepository;
use App\Exportacion\Enums\FormatoExportacion;
use App\Exportacion\Models\Exportacion;
use App\Exportacion\Services\ExportacionService;
use App\Pagos\Repositories\PagoRepository;
use App\Terceros\Models\Tercero;
use App\Terceros\Repositories\TerceroRepository;
use App\Terceros\Services\ConstructorDatosTercero;
use App\Usuarios\Constants\UsuarioConstants;
use App\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClienteService
{
    public function __construct(
        protected ClienteRepository $clientes,
        protected TerceroRepository $terceros,
        protected ConstructorDatosTercero $datosTercero,
        protected CuotaRepository $cuotas,
        protected PagoRepository $pagos,
        protected ClienteEmpleadorHistorialRepository $historialEmpleadores,
        protected AsignacionAsesorService $asignaciones,
        protected EmisorAuditoria $auditoria,
        protected ExportacionService $exportaciones,
    ) {}

    public function solicitarExportacion(
        Usuario $usuario,
        ?string $busqueda,
        ?bool $activo,
        ?EstadoCredito $estadoCredito,
        FormatoExportacion $formato,
    ): Exportacion {
        return $this->exportaciones->solicitar(
            new FuenteClientes((int) $usuario->empresa_id, $usuario->idAsesorConAlcance(), $busqueda, $activo, $estadoCredito?->value),
            $formato,
            $usuario,
        );
    }

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
            $tercero = $this->terceros->obtenerOCrear($this->datosTercero->construir(ClienteConstants::TIPO_PERSONA, $datos));

            $cliente = $this->clientes->crear([
                'tercero_id' => $tercero->id,
                'empleador_id' => $datos['empleador_id'],
                'asesor_id' => $this->asesorDeNuevoCliente($datos),
                'activo' => true,
                'creado_por' => Auth::id(),
            ]);

            $this->asignaciones->registrarInicial($cliente);

            $this->historialEmpleadores->crear([
                'cliente_id' => $cliente->id,
                'empleador_id' => $datos['empleador_id'],
                'fecha_inicio' => now()->toDateString(),
            ]);

            $this->auditoria->registrarCreacion(
                AuditoriaConstants::MODULO_CLIENTES,
                "Cliente {$tercero->nombre} creado",
                $cliente,
                [...$this->datosDelTercero($tercero, $cliente), 'tercero_reutilizado' => ! $tercero->wasRecentlyCreated],
            );

            return $cliente->setAttribute('tercero_reutilizado', ! $tercero->wasRecentlyCreated);
        });
    }

    /** Un asesor que crea un cliente queda como su asesor; cualquier otro rol debe elegirlo (el Request lo exige). */
    private function asesorDeNuevoCliente(array $datos): int
    {
        return Auth::user()->rol?->nombre === UsuarioConstants::ROL_ASESOR ? Auth::id() : (int) $datos['asesor_id'];
    }

    public function editarCliente(Cliente $cliente, array $datos): Cliente
    {
        return DB::transaction(function () use ($cliente, $datos) {
            $anteriores = [...$this->auditoria->instantanea($cliente), ...$this->datosDelTercero($cliente->tercero, $cliente)];

            $this->terceros->actualizar($cliente->tercero, $this->datosTercero->construir(ClienteConstants::TIPO_PERSONA, $datos));

            if ((int) $datos['empleador_id'] !== (int) $cliente->empleador_id) {
                $this->registrarCambioEmpleador($cliente, (int) $datos['empleador_id']);
            }

            $cliente = $this->clientes->actualizar($cliente, [
                'empleador_id' => $datos['empleador_id'],
                ...(array_key_exists('activo', $datos) ? ['activo' => $datos['activo']] : []),
            ]);

            $this->auditoria->registrarModificacion(
                AuditoriaConstants::MODULO_CLIENTES,
                "Cliente {$cliente->tercero->nombre} modificado",
                $cliente,
                $anteriores,
                adicionales: $this->datosDelTercero($cliente->tercero, $cliente),
            );

            return $cliente;
        });
    }

    /** Datos propios del tercero: las columnas que también tiene el cliente (`id`, `empresa_id`, `activo`) ganan las del cliente. */
    private function datosDelTercero(Tercero $tercero, Model $principal): array
    {
        return Arr::except($this->auditoria->instantanea($tercero), array_keys($this->auditoria->instantanea($principal)));
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

    public function eliminar(Cliente $cliente): bool
    {
        if ($cliente->creditos()->whereNotIn('estado', [EstadoCredito::Pagado, EstadoCredito::Cancelado, EstadoCredito::Refinanciado])->exists()) {
            throw ValidationException::withMessages([
                'cliente' => __('validation.custom.cliente.tiene_creditos_activos'),
            ]);
        }

        $eliminado = $this->clientes->eliminar($cliente);

        $this->auditoria->registrarEliminacion(AuditoriaConstants::MODULO_CLIENTES, "Cliente {$cliente->tercero->nombre} eliminado", $cliente);

        return $eliminado;
    }

    public function resumenCliente(Cliente $cliente): array
    {
        $creditos = $cliente->creditos()->orderByDesc('creado_en')->get();
        $resumenCuotas = $this->cuotas->resumenPorCreditos($creditos->pluck('id')->all());

        $saldoConsolidado = Dinero::sumarVarios(...array_map(
            fn (array $fila) => Dinero::desdeCentavos($fila['saldo']),
            $resumenCuotas,
        ));

        $cuotasVencidas = array_sum(array_column($resumenCuotas, 'vencidas'));
        $primerasVencidas = array_filter(array_column($resumenCuotas, 'primera_fecha_vencida'));
        $primeraVencida = $primerasVencidas === [] ? null : min($primerasVencidas);

        return [
            'creditos' => $creditos,
            'saldo_consolidado' => $saldoConsolidado,
            'cartera' => [
                'saldo_en_mora' => Dinero::sumarVarios(...array_map(
                    fn (array $fila) => Dinero::desdeCentavos($fila['saldo_mora']),
                    $resumenCuotas,
                )),
                'cuotas_vencidas' => $cuotasVencidas,
                'dias_mora' => $primeraVencida === null
                    ? 0
                    : max(0, (int) ((strtotime(now()->toDateString()) - strtotime($primeraVencida)) / 86400)),
            ],
            'ultimos_pagos' => $this->pagos->porCliente($cliente->id),
            'documentos' => $cliente->documentos()->orderByDesc('creado_en')->get(),
            'historial_empleadores' => $cliente->historialEmpleadores()->with('empleador')->orderByDesc('fecha_inicio')->get(),
        ];
    }
}
