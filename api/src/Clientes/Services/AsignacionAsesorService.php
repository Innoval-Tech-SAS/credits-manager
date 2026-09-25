<?php

namespace App\Clientes\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Clientes\Models\Cliente;
use App\Clientes\Repositories\ClienteAsesorHistorialRepository;
use App\Clientes\Repositories\ClienteRepository;
use App\Usuarios\Repositories\UsuarioRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** El asesor vigente vive en `clientes.asesor_id`; `cliente_asesor_historial` guarda cada asignación y solo la ve el administrador. */
class AsignacionAsesorService
{
    public function __construct(
        protected ClienteRepository $clientes,
        protected ClienteAsesorHistorialRepository $historial,
        protected UsuarioRepository $usuarios,
        protected EmisorAuditoria $auditoria,
    ) {}

    /** Debe correr dentro de la transacción que crea el cliente. */
    public function registrarInicial(Cliente $cliente, ?string $motivo = null): void
    {
        $this->historial->crear([
            'cliente_id' => $cliente->id,
            'asesor_anterior_id' => null,
            'asesor_id' => $cliente->asesor_id,
            'asignado_por' => Auth::id(),
            'motivo' => $motivo,
        ]);
    }

    public function reasignar(Cliente $cliente, int $asesorId, string $motivo): Cliente
    {
        if ((int) $cliente->asesor_id === $asesorId) {
            throw ValidationException::withMessages([
                'asesor_id' => __('validation.custom.asesor_id.mismo_asesor'),
            ]);
        }

        return DB::transaction(function () use ($cliente, $asesorId, $motivo) {
            $anterior = $cliente->asesor_id;

            $cliente = $this->clientes->actualizar($cliente, ['asesor_id' => $asesorId]);

            $this->historial->crear([
                'cliente_id' => $cliente->id,
                'asesor_anterior_id' => $anterior,
                'asesor_id' => $asesorId,
                'asignado_por' => Auth::id(),
                'motivo' => $motivo,
            ]);

            $nuevo = $this->usuarios->buscarPorIdOFallar($asesorId);
            $this->auditoria->registrar(
                AccionAuditoria::Modificar,
                AuditoriaConstants::MODULO_CLIENTES,
                "Cliente {$cliente->tercero->nombre} reasignado a {$nuevo->nombre}",
                'cliente',
                $cliente->id,
                ['asesor_id' => $anterior],
                ['asesor_id' => $asesorId],
                $motivo,
            );

            return $cliente;
        });
    }

    public function historial(Cliente $cliente): Collection
    {
        return $this->historial->delCliente($cliente->id);
    }

    public function asesoresAsignables(): Collection
    {
        return $this->usuarios->asignables();
    }
}
