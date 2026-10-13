<?php

namespace App\Contabilidad\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Contabilidad\Enums\EstadoComprobante;
use App\Contabilidad\Enums\EstadoPeriodo;
use App\Contabilidad\Enums\EventoContable;
use App\Contabilidad\Enums\NaturalezaCuenta;
use App\Contabilidad\Enums\NaturalezaDocumento;
use App\Contabilidad\Enums\TipoCuenta;
use App\Contabilidad\Models\Comprobante;
use App\Contabilidad\Models\PlanCuenta;
use App\Contabilidad\Models\ReglaContabilizacion;
use App\Contabilidad\Repositories\AplicacionDocumentoRepository;
use App\Contabilidad\Repositories\AsientoContableRepository;
use App\Contabilidad\Repositories\ComprobanteRepository;
use App\Contabilidad\Repositories\ReglaContabilizacionRepository;
use App\Contabilidad\Repositories\TipoComprobanteRepository;
use App\Core\Dinero\Dinero;
use App\Creditos\Models\Credito;
use App\Pagos\Models\Pago;
use App\Terceros\Repositories\TerceroRepository;
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
        protected DocumentoTerceroService $documentosTercero,
        protected AplicacionDocumentoRepository $aplicacionesDocumento,
        protected TerceroRepository $terceros,
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
        ?int $consecutivoManual = null,
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
            $this->exigirRequisitosParametrizados($lineas, $terceroId);
        }

        $periodo = $this->periodos->obtenerAbierto($fecha);

        return DB::transaction(function () use (
            $tipoComprobanteId, $fecha, $lineas, $concepto, $moduloOrigen, $origenId,
            $documentoSoporte, $terceroId, $contabilizarInmediato, $totalDebito,
            $totalCredito, $periodo, $consecutivoManual,
        ) {
            // Dentro de la transacción (no antes, como el diseño anterior): si algo falla después, el
            // ROLLBACK también libera el consecutivo asignado — sin huecos en la numeración.
            ['consecutivo' => $consecutivo, 'ciclo_numeracion' => $ciclo] = $this->tiposComprobante->asignarConsecutivo(
                $tipoComprobanteId,
                $moduloOrigen === 'manual',
                $consecutivoManual,
            );

            $comprobante = $this->comprobantes->crear([
                'tipo_comprobante_id' => $tipoComprobanteId,
                'consecutivo' => $consecutivo,
                'ciclo_numeracion' => $ciclo,
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
                'documento_cruce' => $linea['documento_cruce'] ?? null,
                'fecha_vencimiento' => $linea['fecha_vencimiento'] ?? null,
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
            'documento_cruce' => $linea->documento_cruce,
            'fecha_vencimiento' => $linea->fecha_vencimiento?->toDateString(),
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

            if ($original->modulo_origen === 'manual') {
                $this->reversarDocumentosTercero($original);
            }

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
     * Revisión de un asiento manual. Un borrador no afecta reportes, así que la revisión no cambia saldos; solo
     * deja constancia de quién lo revisó antes de contabilizarlo.
     */
    public function revisar(Comprobante $comprobante): Comprobante
    {
        if ($comprobante->estado !== EstadoComprobante::Borrador) {
            throw ValidationException::withMessages(['comprobante' => __('validation.custom.comprobante.no_borrador')]);
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

            if ($comprobante->modulo_origen === 'manual') {
                $this->procesarDocumentosTerceroAlContabilizar($comprobante);
            }

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

    /**
     * Conecta el comprobante manual al motor genérico de `DocumentoTerceroService`: una línea sobre una cuenta
     * marcada `exige_tercero` + `exige_documento_cruce` (cartera o proveedores reales, no retenciones ni bancos)
     * causa un documento con saldo la primera vez que aparece ese documento cruce, y lo cruza (abona) las veces
     * siguientes. La dirección se deriva de la propia cuenta: `naturaleza` (débito/crédito) dice qué lado "aumenta"
     * el saldo, y `tipo` (activo/pasivo) dice si el documento es por cobrar o por pagar. Corre en `contabilizar()`,
     * no en `crearComprobante()`, porque un borrador no tiene efecto contable todavía.
     */
    private function procesarDocumentosTerceroAlContabilizar(Comprobante $comprobante): void
    {
        $comprobante->loadMissing(['lineas.cuenta', 'tipoComprobante']);

        foreach ($comprobante->lineas as $linea) {
            $cuenta = $linea->cuenta;

            if (! $cuenta->exige_tercero || ! $cuenta->exige_documento_cruce || empty($linea->documento_cruce)) {
                continue;
            }

            $terceroId = $linea->tercero_id ?? $comprobante->tercero_id;
            $naturalezaDocumento = match ($cuenta->tipo) {
                TipoCuenta::Activo => NaturalezaDocumento::PorCobrar,
                TipoCuenta::Pasivo => NaturalezaDocumento::PorPagar,
                default => throw ValidationException::withMessages([
                    'comprobante' => __('validation.custom.comprobante.cuenta_no_apta_para_documento_cruce', ['cuenta' => $cuenta->codigo]),
                ]),
            };

            $aumentaElSaldo = ($cuenta->naturaleza === NaturalezaCuenta::Debito && $linea->debito->esPositivo())
                || ($cuenta->naturaleza === NaturalezaCuenta::Credito && $linea->credito->esPositivo());
            $monto = $linea->debito->esPositivo() ? $linea->debito : $linea->credito;

            $existente = $this->documentosTercero->buscarPorNumero($terceroId, $naturalezaDocumento, $linea->documento_cruce);

            if ($aumentaElSaldo) {
                if ($existente) {
                    throw ValidationException::withMessages([
                        'comprobante' => __('validation.custom.comprobante.documento_cruce_duplicado', ['documento' => $linea->documento_cruce]),
                    ]);
                }

                $this->documentosTercero->crearDocumento(
                    $this->terceros->buscarPorIdOFallar($terceroId),
                    $naturalezaDocumento,
                    $comprobante->tipoComprobante->codigo,
                    $linea->documento_cruce,
                    $comprobante->fecha->toDateString(),
                    $linea->descripcion ?? $comprobante->concepto,
                    $monto,
                    'contabilidad_manual',
                    $comprobante->id,
                    planCuentaId: $cuenta->id,
                    fechaVencimiento: $linea->fecha_vencimiento?->toDateString(),
                );
            } else {
                if (! $existente) {
                    throw ValidationException::withMessages([
                        'comprobante' => __('validation.custom.comprobante.documento_cruce_no_encontrado', ['documento' => $linea->documento_cruce]),
                    ]);
                }

                $this->documentosTercero->aplicarValor(
                    $existente->tercero,
                    $naturalezaDocumento,
                    [['documento_tercero_id' => $existente->id, 'valor' => $monto]],
                    'contabilidad_manual',
                    $comprobante->id,
                    $comprobante->fecha->toDateString(),
                );
            }
        }
    }

    /**
     * Deshace, al reversar, los efectos que `procesarDocumentosTerceroAlContabilizar()` causó: restaura el saldo
     * de los documentos que este comprobante cruzó (abonó) y anula los que él mismo creó — salvo que alguno de
     * esos ya tenga un abono vigente de otro origen, caso en el que se bloquea la reversa (ver `GastoService::anular`
     * para el mismo guard, y `EgresoService::reversar` para el mismo patrón de restaurar saldo).
     */
    private function reversarDocumentosTercero(Comprobante $original): void
    {
        $documentosCreados = $this->documentosTercero->creadosPorOrigen('contabilidad_manual', $original->id);

        foreach ($documentosCreados as $documento) {
            if ($this->aplicacionesDocumento->existeVigentePara($documento->id)) {
                throw ValidationException::withMessages([
                    'comprobante' => __('validation.custom.comprobante.no_se_puede_reversar_documento_con_pagos', ['documento' => $documento->numero_documento]),
                ]);
            }
        }

        $vigentes = $this->aplicacionesDocumento->vigentesDe('contabilidad_manual', $original->id);

        if ($vigentes->isNotEmpty()) {
            $documentos = $this->documentosTercero->buscarVariosPorId($vigentes->pluck('documento_tercero_id')->unique()->all())->keyBy('id');

            foreach ($vigentes as $aplicacion) {
                $this->documentosTercero->incrementarValor(
                    $documentos[$aplicacion->documento_tercero_id],
                    $aplicacion->valor_aplicado,
                    'contabilidad_manual_reverso',
                    $original->id,
                );
            }

            $this->aplicacionesDocumento->marcarReversadas($vigentes->pluck('id')->all());
        }

        foreach ($documentosCreados as $documento) {
            $this->documentosTercero->anular($documento);
        }
    }

    /**
     * Cada cuenta declara qué exige al usarse en un asiento manual (centro de costo, tercero, documento cruce,
     * vencimiento). El tercero se resuelve igual que al armar las líneas (`$linea['tercero_id'] ?? $terceroId`,
     * el del comprobante si la línea no trae uno propio); documento cruce y vencimiento son propios de cada
     * línea. Los flujos automáticos resuelven esos datos por su cuenta y no pasan por aquí.
     */
    private function exigirRequisitosParametrizados(array $lineas, ?int $terceroId): void
    {
        $cuentas = PlanCuenta::query()->whereIn('id', array_column($lineas, 'cuenta_id'))->get()->keyBy('id');

        foreach ($lineas as $linea) {
            $cuenta = $cuentas[$linea['cuenta_id']] ?? null;

            if (! $cuenta) {
                continue;
            }

            if ($cuenta->exige_centro_costo && empty($linea['centro_costo_id'])) {
                throw ValidationException::withMessages(['lineas' => __('validation.custom.lineas.centro_costo_requerido')]);
            }

            if ($cuenta->exige_tercero && empty($linea['tercero_id'] ?? $terceroId)) {
                throw ValidationException::withMessages(['lineas' => __('validation.custom.lineas.tercero_requerido')]);
            }

            if ($cuenta->exige_documento_cruce && empty($linea['documento_cruce'])) {
                throw ValidationException::withMessages(['lineas' => __('validation.custom.lineas.documento_cruce_requerido')]);
            }

            if ($cuenta->exige_vencimiento && empty($linea['fecha_vencimiento'])) {
                throw ValidationException::withMessages(['lineas' => __('validation.custom.lineas.vencimiento_requerido')]);
            }
        }
    }

    // -------- Generadores automáticos por evento de negocio --------

    public function contabilizarDesembolsoCredito(Credito $credito): ?Comprobante
    {
        // En una refinanciación el saldo tomado ya está en cartera: solo el dinero nuevo mueve caja y cartera.
        $valor = $credito->valorDesembolsado();

        if (! $valor->esPositivo()) {
            return null;
        }

        $regla = $this->reglaActiva(EventoContable::DesembolsoCredito);

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
        if (! $valor->esPositivo()) {
            return null;
        }

        $regla = $this->reglaActiva(EventoContable::DesembolsoCredito);

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

    /** Si hay algo que contabilizar, exige las tres reglas del recaudo (capital, interés y, si aplica, otros gastos): nunca se recauda en silencio sin asiento. */
    public function contabilizarRecaudoPago(Pago $pago, Dinero $capital, Dinero $interes, ?Dinero $otros = null): ?Comprobante
    {
        $otros ??= Dinero::cero();

        $aplicado = $capital->sumar($interes)->sumar($otros);
        if (! $aplicado->esPositivo()) {
            return null;
        }

        $reglaCapital = $this->reglaActiva(EventoContable::RecaudoCapital);
        $reglaInteres = $this->reglaActiva(EventoContable::RecaudoInteres);
        $reglaOtros = $otros->esPositivo() ? $this->reglaActiva(EventoContable::RecaudoOtrosGastos) : null;

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

    /** Sin regla activa la operación no se confirma: nunca se desembolsa ni se recauda sin que quede su asiento. */
    private function reglaActiva(EventoContable $evento): ReglaContabilizacion
    {
        $regla = $this->reglas->activaParaEvento($evento);

        if ($regla === null) {
            throw ValidationException::withMessages([
                'contabilidad' => __('validation.custom.contabilidad.sin_regla_contable', ['evento' => $evento->etiqueta()]),
            ]);
        }

        return $regla;
    }
}
