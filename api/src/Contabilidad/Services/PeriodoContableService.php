<?php

namespace App\Contabilidad\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Contabilidad\Enums\EstadoPeriodo;
use App\Contabilidad\Models\PeriodoContable;
use App\Contabilidad\Repositories\PeriodoContableRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class PeriodoContableService
{
    public function __construct(
        protected PeriodoContableRepository $periodos,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function listar(): Collection
    {
        return $this->periodos->todosOrdenados();
    }

    /**
     * Obtiene (o crea, abierto por defecto) el período contable de la fecha dada y
     * exige que esté abierto — igual que `obtenerPeriodoAbierto()` del legado.
     */
    public function obtenerAbierto(string $fecha): PeriodoContable
    {
        $anio = (int) date('Y', strtotime($fecha));
        $mes = (int) date('m', strtotime($fecha));
        $periodo = $this->periodos->obtenerOCrear($anio, $mes);

        if ($periodo->estado === EstadoPeriodo::Cerrado) {
            throw ValidationException::withMessages([
                'fecha' => __('validation.custom.fecha.periodo_cerrado', ['mes' => $mes, 'anio' => $anio]),
            ]);
        }

        return $periodo;
    }

    public function cerrar(PeriodoContable $periodo): PeriodoContable
    {
        $anteriores = $this->auditoria->instantanea($periodo);

        $periodo = $this->periodos->actualizar($periodo, [
            'estado' => EstadoPeriodo::Cerrado,
            'cerrado_por' => Auth::id(),
            'cerrado_en' => now(),
        ]);

        $this->auditoria->registrarModificacion(AuditoriaConstants::MODULO_CONTABILIDAD, "Período {$periodo->mes}/{$periodo->anio} cerrado", $periodo, $anteriores, accion: AccionAuditoria::CerrarPeriodo);

        return $periodo;
    }

    /** Requiere permiso `contabilidad,eliminar` (el más privilegiado) en la ruta. */
    public function reabrir(PeriodoContable $periodo, string $motivo): PeriodoContable
    {
        $anteriores = $this->auditoria->instantanea($periodo);

        $periodo = $this->periodos->actualizar($periodo, [
            'estado' => EstadoPeriodo::Abierto,
            'reabierto_motivo' => $motivo,
        ]);

        $this->auditoria->registrarModificacion(AuditoriaConstants::MODULO_CONTABILIDAD, "Período {$periodo->mes}/{$periodo->anio} reabierto", $periodo, $anteriores, $motivo, AccionAuditoria::ReabrirPeriodo);

        return $periodo;
    }
}
