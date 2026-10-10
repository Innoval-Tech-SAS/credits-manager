<?php

namespace App\Conciliacion\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Conciliacion\Enums\EstadoExtracto;
use App\Conciliacion\Models\ExtractoBancario;
use App\Conciliacion\Repositories\ExtractoBancarioRepository;
use App\Conciliacion\Repositories\MovimientoExtractoRepository;
use App\Contabilidad\Services\ReporteContableService;
use App\Usuarios\Models\Usuario;
use Illuminate\Validation\ValidationException;

/**
 * Cierre y reapertura de un extracto ya cargado (Fase 6.3). El cierre exige que no queden movimientos
 * pendientes y que el saldo según el libro (la cuenta PUC del banco, calculada con el mismo signo que el
 * resto de los reportes contables) coincida con `saldo_actual` del extracto — el criterio de aceptación
 * central de la conciliación bancaria. La reapertura es una acción directa de un solo paso, igual que
 * `PeriodoContableService::reabrir()`.
 */
class CierreExtractoService
{
    public function __construct(
        protected ExtractoBancarioRepository $extractos,
        protected MovimientoExtractoRepository $movimientos,
        protected ReporteContableService $reportes,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function cerrar(ExtractoBancario $extracto, Usuario $usuario): ExtractoBancario
    {
        if ($extracto->estado !== EstadoExtracto::Cargado) {
            throw ValidationException::withMessages(['extracto' => __('validation.custom.conciliacion.extracto_no_cargado')]);
        }

        if ($this->movimientos->contarPendientes($extracto->id) > 0) {
            throw ValidationException::withMessages(['extracto' => __('validation.custom.conciliacion.movimientos_pendientes')]);
        }

        $extracto->loadMissing('cuentaBancaria.planCuenta');

        $saldoLibro = $this->reportes->saldoInicialDeCuenta(
            (int) $extracto->empresa_id,
            $extracto->cuentaBancaria->planCuenta,
            $extracto->fecha_hasta->addDay()->toDateString(),
        );

        if (! $saldoLibro->igualA($extracto->saldo_actual)) {
            throw ValidationException::withMessages(['extracto' => __('validation.custom.conciliacion.cierre_no_cuadra', [
                'saldo_libro' => number_format($saldoLibro->aFloat(), 2),
                'saldo_extracto' => number_format($extracto->saldo_actual->aFloat(), 2),
            ])]);
        }

        $anteriores = $this->auditoria->instantanea($extracto);

        $extracto = $this->extractos->actualizar($extracto, [
            'estado' => EstadoExtracto::Cerrado,
            'cerrado_por' => $usuario->id,
            'cerrado_en' => now(),
        ]);

        $this->auditoria->registrarModificacion(
            AuditoriaConstants::MODULO_CONCILIACION,
            "Extracto bancario #{$extracto->id} cerrado",
            $extracto,
            $anteriores,
            accion: AccionAuditoria::CerrarPeriodo,
        );

        return $extracto;
    }

    /** Acción directa de un solo paso: no pasa por el flujo de solicitud/aprobación de los períodos contables. */
    public function reabrir(ExtractoBancario $extracto, Usuario $usuario, string $motivo): ExtractoBancario
    {
        if ($extracto->estado !== EstadoExtracto::Cerrado) {
            throw ValidationException::withMessages(['extracto' => __('validation.custom.conciliacion.extracto_no_cerrado')]);
        }

        $anteriores = $this->auditoria->instantanea($extracto);

        $extracto = $this->extractos->actualizar($extracto, [
            'estado' => EstadoExtracto::Cargado,
            'reabierto_por' => $usuario->id,
            'reabierto_en' => now(),
            'motivo_reapertura' => $motivo,
        ]);

        $this->auditoria->registrarModificacion(
            AuditoriaConstants::MODULO_CONCILIACION,
            "Extracto bancario #{$extracto->id} reabierto",
            $extracto,
            $anteriores,
            motivo: $motivo,
            accion: AccionAuditoria::ReabrirPeriodo,
        );

        return $extracto;
    }
}
