<?php

namespace App\Contabilidad\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Contabilidad\Enums\EstadoComprobante;
use App\Contabilidad\Enums\EstadoPeriodo;
use App\Contabilidad\Enums\EventoContable;
use App\Contabilidad\Models\Comprobante;
use App\Contabilidad\Models\PlanCuenta;
use App\Contabilidad\Repositories\AsientoContableRepository;
use App\Contabilidad\Repositories\ComprobanteRepository;
use App\Contabilidad\Repositories\ReglaContabilizacionRepository;
use App\Contabilidad\Repositories\TipoComprobanteRepository;
use App\Core\Dinero\Dinero;
use App\Creditos\Models\Credito;
use App\Pagos\Models\Pago;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Puerto directo del motor de `includes/contabilidad.php` del legado. Toda
 * generación automática (créditos, pagos) pasa por `crearComprobante()`, que:
 *  - exige período contable abierto
 *  - valida SIEMPRE que total débitos = total créditos antes de contabilizar
 *  - nunca permite eliminar un comprobante contabilizado (solo reverso/anulación)
 */
class ComprobanteService
{
    public function __construct(
        protected ComprobanteRepository $comprobantes,
        protected AsientoContableRepository $asientos,
        protected TipoComprobanteRepository $tiposComprobante,
        protected ReglaContabilizacionRepository $reglas,
        protected PeriodoContableService $periodos,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function listar(?string $busqueda, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->comprobantes->paginar($busqueda, max(1, min(100, $porPagina)), max(1, $pagina));
    }

    public function mostrar(Comprobante $comprobante): Comprobante
    {
        return $this->comprobantes->obtenerConLineas($comprobante);
    }

    public function buscarContabilizadoPorOrigen(string $moduloOrigen, int $origenId): ?Comprobante
    {
        return $this->comprobantes->buscarContabilizadoPorOrigen($moduloOrigen, $origenId);
    }

    /**
     * Crea y contabiliza (o deja en borrador) un comprobante con sus líneas.
     * Lanza ValidationException si los totales no cuadran o el período está cerrado.
     */
    public function crearComprobante(
        int $tipoComprobanteId,
        string $fecha,
        array $lineas,
        string $concepto,
        ?string $moduloOrigen = null,
        ?int $origenId = null,
        ?string $documentoSoporte = null,
        ?int $terceroId = null,
        bool $contabilizarInmediato = true,
    ): Comprobante {
        $lineas = array_map(fn (array $linea) => [
            ...$linea,
            'debito' => self::aDinero($linea['debito'] ?? 0),
            'credito' => self::aDinero($linea['credito'] ?? 0),
        ], $lineas);

        $totalDebito = Dinero::sumarVarios(...array_column($lineas, 'debito'));
        $totalCredito = Dinero::sumarVarios(...array_column($lineas, 'credito'));

        if (! $totalDebito->igualA($totalCredito)) {
            throw ValidationException::withMessages([
                'lineas' => __('validation.custom.lineas.descuadrado', [
                    'debito' => number_format($totalDebito->aFloat(), 2),
                    'credito' => number_format($totalCredito->aFloat(), 2),
                ]),
            ]);
        }

        if (! $totalDebito->esPositivo()) {
            throw ValidationException::withMessages([
                'lineas' => __('validation.custom.lineas.sin_valor'),
            ]);
        }

        if ($moduloOrigen === 'manual') {
            $this->exigirCentroCostoSegunCuenta($lineas);
        }

        $periodo = $this->periodos->obtenerAbierto($fecha);
        $consecutivo = $this->tiposComprobante->incrementarConsecutivo($tipoComprobanteId);

        return DB::transaction(function () use (
            $tipoComprobanteId, $fecha, $lineas, $concepto, $moduloOrigen, $origenId,
            $documentoSoporte, $terceroId, $contabilizarInmediato, $totalDebito,
            $totalCredito, $periodo, $consecutivo,
        ) {
            $comprobante = $this->comprobantes->crear([
                'tipo_comprobante_id' => $tipoComprobanteId,
                'consecutivo' => $consecutivo,
                'periodo_id' => $periodo->id,
                'fecha' => $fecha,
                'tercero_id' => $terceroId,
                'concepto' => $concepto,
                'modulo_origen' => $moduloOrigen,
                'origen_id' => $origenId,
                'documento_soporte' => $documentoSoporte,
                'estado' => $contabilizarInmediato ? EstadoComprobante::Contabilizado : EstadoComprobante::Borrador,
                'total_debito' => $totalDebito,
                'total_credito' => $totalCredito,
                'creado_por' => Auth::id(),
            ]);

            $this->asientos->crearVarias($comprobante->id, array_map(fn (array $linea) => [
                'cuenta_id' => $linea['cuenta_id'],
                'tercero_id' => $linea['tercero_id'] ?? $terceroId,
                'centro_costo_id' => $linea['centro_costo_id'] ?? null,
                'debito' => $linea['debito'],
                'credito' => $linea['credito'],
                'descripcion' => $linea['descripcion'] ?? $concepto,
            ], $lineas));

            if ($contabilizarInmediato) {
                $this->comprobantes->actualizar($comprobante, [
                    'contabilizado_por' => Auth::id(),
                    'contabilizado_en' => now(),
                ]);
            }

            $origen = $moduloOrigen ? " (origen: {$moduloOrigen}".($origenId ? " #{$origenId}" : '').')' : '';
            $this->auditoria->registrarCreacion(
                AuditoriaConstants::MODULO_CONTABILIDAD,
                'Comprobante #'.$comprobante->consecutivo.($contabilizarInmediato ? ' contabilizado' : ' creado en borrador')." por {$totalDebito->aDecimalString()}{$origen}",
                $comprobante,
                ['lineas' => count($lineas)],
                $contabilizarInmediato ? AccionAuditoria::Contabilizar : AccionAuditoria::Crear,
            );

            return $comprobante;
        });
    }

    /**
     * Reversa un comprobante contabilizado generando uno nuevo con las líneas
     * invertidas. El original NUNCA se elimina ni se modifica salvo su estado.
     */
    public function reversarComprobante(Comprobante $original, string $motivo): Comprobante
    {
        if (in_array($original->estado, [EstadoComprobante::Borrador, EstadoComprobante::Revisado], true)) {
            return $this->anularSinEfecto($original, $motivo);
        }

        if ($original->estado !== EstadoComprobante::Contabilizado) {
            throw ValidationException::withMessages([
                'comprobante' => __('validation.custom.comprobante.no_contabilizado'),
            ]);
        }

        if ($original->periodo->estado === EstadoPeriodo::Cerrado) {
            throw ValidationException::withMessages([
                'comprobante' => __('validation.custom.comprobante.periodo_cerrado'),
            ]);
        }

        $lineasReverso = $original->lineas->map(fn ($linea) => [
            'cuenta_id' => $linea->cuenta_id,
            'tercero_id' => $linea->tercero_id,
            'centro_costo_id' => $linea->centro_costo_id,
            'debito' => $linea->credito,
            'credito' => $linea->debito,
            'descripcion' => 'Reverso: '.$linea->descripcion,
        ])->all();

        return DB::transaction(function () use ($original, $motivo, $lineasReverso) {
            $anteriores = $this->auditoria->instantanea($original);

            $reverso = $this->crearComprobante(
                $original->tipo_comprobante_id,
                now()->toDateString(),
                $lineasReverso,
                'REVERSO - '.$motivo,
                'reverso',
                $original->id,
                'Reverso de comprobante #'.$original->consecutivo,
                $original->tercero_id,
            );

            $this->comprobantes->actualizar($original, [
                'comprobante_reverso_id' => $reverso->id,
                'estado' => EstadoComprobante::Anulado,
                'anulado_por' => Auth::id(),
                'anulado_en' => now(),
            ]);

            $this->auditoria->registrarModificacion(
                AuditoriaConstants::MODULO_CONTABILIDAD,
                'Comprobante #'.$original->consecutivo.' reversado por el #'.$reverso->consecutivo,
                $original,
                $anteriores,
                $motivo,
                AccionAuditoria::Reversar,
            );

            return $reverso;
        });
    }

    /**
     * Revisión de un asiento manual: la hace otro usuario que el creador (segregación de funciones). Un borrador no
     * afecta reportes, así que la revisión no cambia saldos; solo deja constancia de quién lo revisó.
     */
    public function revisar(Comprobante $comprobante): Comprobante
    {
        if ($comprobante->estado !== EstadoComprobante::Borrador) {
            throw ValidationException::withMessages(['comprobante' => __('validation.custom.comprobante.no_borrador')]);
        }

        if ((int) $comprobante->creado_por === (int) Auth::id()) {
            throw ValidationException::withMessages(['comprobante' => __('validation.custom.comprobante.revision_propia')]);
        }

        $this->periodos->obtenerAbierto($comprobante->fecha->toDateString());

        return DB::transaction(function () use ($comprobante) {
            $anteriores = $this->auditoria->instantanea($comprobante);

            $comprobante = $this->comprobantes->actualizar($comprobante, [
                'estado' => EstadoComprobante::Revisado,
                'revisado_por' => Auth::id(),
                'revisado_en' => now(),
            ]);

            $this->auditoria->registrarModificacion(
                AuditoriaConstants::MODULO_CONTABILIDAD,
                'Comprobante #'.$comprobante->consecutivo.' revisado',
                $comprobante,
                $anteriores,
                accion: AccionAuditoria::Revisar,
            );

            return $comprobante;
        });
    }

    /** Publica un asiento revisado. Vuelve a comprobar el cuadre línea por línea: el total guardado no basta. */
    public function contabilizar(Comprobante $comprobante): Comprobante
    {
        if ($comprobante->estado !== EstadoComprobante::Revisado) {
            throw ValidationException::withMessages(['comprobante' => __('validation.custom.comprobante.no_revisado')]);
        }

        $this->periodos->obtenerAbierto($comprobante->fecha->toDateString());

        $comprobante->loadMissing('lineas');
        $debito = Dinero::sumarVarios(...$comprobante->lineas->pluck('debito')->all());
        $credito = Dinero::sumarVarios(...$comprobante->lineas->pluck('credito')->all());

        if (! $debito->igualA($credito) || ! $debito->igualA($comprobante->total_debito) || ! $credito->igualA($comprobante->total_credito)) {
            throw ValidationException::withMessages(['comprobante' => __('validation.custom.comprobante.descuadrado_al_contabilizar')]);
        }

        return DB::transaction(function () use ($comprobante, $debito) {
            $anteriores = $this->auditoria->instantanea($comprobante);

            $comprobante = $this->comprobantes->actualizar($comprobante, [
                'estado' => EstadoComprobante::Contabilizado,
                'contabilizado_por' => Auth::id(),
                'contabilizado_en' => now(),
            ]);

            $this->auditoria->registrarModificacion(
                AuditoriaConstants::MODULO_CONTABILIDAD,
                'Comprobante #'.$comprobante->consecutivo.' contabilizado por '.$debito->aDecimalString(),
                $comprobante,
                $anteriores,
                accion: AccionAuditoria::Contabilizar,
            );

            return $comprobante;
        });
    }

    /** Un borrador o revisado nunca llegó a los reportes: se anula sin reverso ni asiento. */
    private function anularSinEfecto(Comprobante $comprobante, string $motivo): Comprobante
    {
        return DB::transaction(function () use ($comprobante, $motivo) {
            $anteriores = $this->auditoria->instantanea($comprobante);

            $comprobante = $this->comprobantes->actualizar($comprobante, [
                'estado' => EstadoComprobante::Anulado,
                'anulado_por' => Auth::id(),
                'anulado_en' => now(),
            ]);

            $this->auditoria->registrarModificacion(
                AuditoriaConstants::MODULO_CONTABILIDAD,
                'Comprobante #'.$comprobante->consecutivo.' anulado sin efecto contable',
                $comprobante,
                $anteriores,
                $motivo,
                AccionAuditoria::Anular,
            );

            return $comprobante;
        });
    }

    /** Una cuenta marcada `exige_centro_costo` lo exige en cada línea que la use, también en un asiento manual. */
    private function exigirCentroCostoSegunCuenta(array $lineas): void
    {
        $cuentas = PlanCuenta::query()->whereIn('id', array_column($lineas, 'cuenta_id'))->get()->keyBy('id');

        foreach ($lineas as $linea) {
            $exigeCentroCosto = $cuentas[$linea['cuenta_id']]->exige_centro_costo ?? false;

            if ($exigeCentroCosto && empty($linea['centro_costo_id'])) {
                throw ValidationException::withMessages(['lineas' => __('validation.custom.lineas.centro_costo_requerido')]);
            }
        }
    }

    // -------- Generadores automáticos por evento de negocio --------

    public function contabilizarDesembolsoCredito(Credito $credito): ?Comprobante
    {
        $regla = $this->reglas->activaParaEvento(EventoContable::DesembolsoCredito);
        // En una refinanciación el saldo tomado ya está en cartera: solo el dinero nuevo mueve caja y cartera.
        $valor = $credito->valorDesembolsado();

        if (! $regla || ! $valor->esPositivo()) {
            return null;
        }

        return $this->crearComprobante(
            $regla->tipo_comprobante_id,
            $credito->fecha_desembolso->toDateString(),
            [
                ['cuenta_id' => $regla->cuenta_debito_id, 'debito' => $valor, 'credito' => 0],
                ['cuenta_id' => $regla->cuenta_credito_id, 'debito' => 0, 'credito' => $valor],
            ],
            'Desembolso crédito #'.$credito->id,
            'creditos',
            $credito->id,
        );
    }

    /**
     * `$capital`/`$interes` se derivan en `PagoService::registrarPago()` a partir de
     * `cuotas.valor_capital`/`valor_interes` (regla "interés primero") — el legado los
     * sumaba de `pago_detalle`, que en este sistema no guarda ese desglose por línea.
     * Se contabiliza `capital + interes` (lo efectivamente aplicado a cuotas) en la
     * línea débito, no `pago.valor_pago`: si hay sobrante (que `PagoService` ya
     * descarta silenciosamente), usar el valor completo del pago haría que el
     * comprobante nunca cuadrara.
     */
    /**
     * Asiento de apertura de la cartera importada: el capital desembolsado queda en la cuenta de cartera de la regla
     * de desembolso, contra la cuenta de contrapartida que elige la carga. Reemplaza los asientos de desembolso
     * individuales, que la importación histórica no genera.
     */
    public function eventosContablesActivos(EventoContable ...$eventos): bool
    {
        foreach ($eventos as $evento) {
            if ($this->reglas->activaParaEvento($evento) === null) {
                return false;
            }
        }

        return true;
    }

    public function contabilizarAperturaCartera(Dinero $valor, int $cuentaContrapartidaId, string $fecha, int $importacionId): ?Comprobante
    {
        $regla = $this->reglas->activaParaEvento(EventoContable::DesembolsoCredito);

        if (! $regla || ! $valor->esPositivo()) {
            return null;
        }

        return $this->crearComprobante(
            $regla->tipo_comprobante_id,
            $fecha,
            [
                ['cuenta_id' => $regla->cuenta_debito_id, 'debito' => $valor, 'credito' => 0],
                ['cuenta_id' => $cuentaContrapartidaId, 'debito' => 0, 'credito' => $valor],
            ],
            'Apertura de cartera importada #'.$importacionId,
            'importaciones',
            $importacionId,
        );
    }

    /** Sin regla activa de otros gastos no se contabiliza un recaudo que los incluye: el asiento no cuadraría. */
    public function contabilizarRecaudoPago(Pago $pago, Dinero $capital, Dinero $interes, ?Dinero $otros = null): ?Comprobante
    {
        $otros ??= Dinero::cero();

        $reglaCapital = $this->reglas->activaParaEvento(EventoContable::RecaudoCapital);
        $reglaInteres = $this->reglas->activaParaEvento(EventoContable::RecaudoInteres);
        $reglaOtros = $otros->esPositivo() ? $this->reglas->activaParaEvento(EventoContable::RecaudoOtrosGastos) : null;
        if (! $reglaCapital || ! $reglaInteres || ($otros->esPositivo() && ! $reglaOtros)) {
            return null;
        }

        $aplicado = $capital->sumar($interes)->sumar($otros);
        if (! $aplicado->esPositivo()) {
            return null;
        }

        $lineas = [
            ['cuenta_id' => $reglaCapital->cuenta_debito_id, 'debito' => $aplicado, 'credito' => 0],
        ];
        if ($capital->esPositivo()) {
            $lineas[] = ['cuenta_id' => $reglaCapital->cuenta_credito_id, 'debito' => 0, 'credito' => $capital];
        }
        if ($interes->esPositivo()) {
            $lineas[] = ['cuenta_id' => $reglaInteres->cuenta_credito_id, 'debito' => 0, 'credito' => $interes];
        }
        if ($otros->esPositivo()) {
            $lineas[] = ['cuenta_id' => $reglaOtros->cuenta_credito_id, 'debito' => 0, 'credito' => $otros];
        }

        return $this->crearComprobante(
            $reglaCapital->tipo_comprobante_id,
            $pago->fecha_pago->toDateString(),
            $lineas,
            'Recaudo pago #'.$pago->id,
            'pagos',
            $pago->id,
        );
    }

    private static function aDinero(Dinero|int|float|string $valor): Dinero
    {
        return $valor instanceof Dinero ? $valor : Dinero::desdePesos($valor);
    }
}
