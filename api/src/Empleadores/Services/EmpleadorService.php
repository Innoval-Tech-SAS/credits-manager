<?php

namespace App\Empleadores\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Services\EmisorAuditoria;
use App\Empleadores\Models\Empleador;
use App\Empleadores\Repositories\EmpleadorRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class EmpleadorService
{
    public function __construct(
        protected EmpleadorRepository $empleadores,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function listarEmpleadores(?string $busqueda, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->empleadores->paginar($busqueda, max(1, min(100, $porPagina)), max(1, $pagina));
    }

    public function crear(array $datos): Empleador
    {
        $empleador = $this->empleadores->crear([
            'nombre' => $datos['nombre'],
            'nit' => $datos['nit'] ?? null,
            'direccion' => $datos['direccion'] ?? null,
            'telefono' => $datos['telefono'] ?? null,
        ]);

        $this->auditoria->registrarCreacion(AuditoriaConstants::MODULO_EMPLEADORES, "Empleador {$empleador->nombre} creado", $empleador);

        return $empleador;
    }

    public function editar(Empleador $empleador, array $datos): Empleador
    {
        $anteriores = $this->auditoria->instantanea($empleador);

        $empleador = $this->empleadores->actualizar($empleador, [
            'nombre' => $datos['nombre'],
            'nit' => $datos['nit'] ?? null,
            'direccion' => $datos['direccion'] ?? null,
            'telefono' => $datos['telefono'] ?? null,
        ]);

        $this->auditoria->registrarModificacion(AuditoriaConstants::MODULO_EMPLEADORES, "Empleador {$empleador->nombre} modificado", $empleador, $anteriores);

        return $empleador;
    }

    public function eliminar(Empleador $empleador): bool
    {
        if ($empleador->clientes()->exists()) {
            throw ValidationException::withMessages([
                'empleador' => __('validation.custom.empleador.tiene_clientes_asignados'),
            ]);
        }

        $eliminado = $this->empleadores->eliminar($empleador);

        $this->auditoria->registrarEliminacion(AuditoriaConstants::MODULO_EMPLEADORES, "Empleador {$empleador->nombre} eliminado", $empleador);

        return $eliminado;
    }
}
