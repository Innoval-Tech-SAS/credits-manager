<?php

namespace App\Conciliacion\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Conciliacion\Enums\EstadoMovimientoExtracto;
use App\Conciliacion\Enums\TipoMarcaConciliacion;
use App\Conciliacion\Models\ExtractoBancario;
use App\Conciliacion\Models\MarcaConciliacion;
use App\Conciliacion\Models\MovimientoExtracto;
use App\Conciliacion\Repositories\MarcaConciliacionRepository;
use App\Conciliacion\Repositories\MovimientoExtractoRepository;
use App\Contabilidad\Models\AsientoContable;
use App\Contabilidad\Repositories\AsientoContableRepository;
use App\Contabilidad\Services\ComprobanteService;
use App\Core\Dinero\Dinero;
use App\Usuarios\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Matching entre los movimientos pendientes de un extracto y los asientos de la cuenta PUC del banco: marcar
 * una pareja, enviar un movimiento sin contraparte a la cuenta puente, o desmarcar (reversa) cualquiera de
 * las dos acciones anteriores. El esquema (`marcas_conciliacion`) ya existe desde la Fase 6.1.
 */
class MatchConciliacionService
{
    /** Ventana de fecha para sugerir candidatos: un movimiento del extracto puede llegar al libro unos días después. */
    private const VENTANA_DIAS = 3;

    public function __construct(
        protected MarcaConciliacionRepository $marcas,
        protected MovimientoExtractoRepository $movimientos,
        protected AsientoContableRepository $asientos,
        protected ComprobanteService $comprobantes,
        protected EmisorAuditoria $auditoria,
    ) {}

    /** @return array{extracto: ExtractoBancario, movimientos: list<MovimientoExtracto>, asientos: Collection, sugerencias: list<array>} */
    public function candidatos(ExtractoBancario $extracto): array
    {
        $extracto->loadMissing('cuentaBancaria');
        $cuenta = $extracto->cuentaBancaria;

        $movimientos = $extracto->movimientos()->with('marcaVigente')->orderBy('orden')->get();
        $pendientes = $movimientos->where('estado', EstadoMovimientoExtracto::Pendiente);

        $asientos = $this->asientos->candidatosConciliables(
            (int) $extracto->empresa_id,
            $cuenta->plan_cuenta_id,
            $extracto->fecha_desde->subDays(self::VENTANA_DIAS)->toDateString(),
            $extracto->fecha_hasta->addDays(self::VENTANA_DIAS)->toDateString(),
        );

        $sugerencias = $pendientes->map(function (MovimientoExtracto $movimiento) use ($asientos) {
            $enVentana = $asientos->filter(
                fn (AsientoContable $asiento) => abs(CarbonImmutable::parse($asiento->fecha)->diffInDays($movimiento->fecha)) <= self::VENTANA_DIAS,
            );
            $mismoValor = $enVentana->filter(fn (AsientoContable $asiento) => $this->valorAsiento($asiento)->igualA($movimiento->valor->absoluto()));

            return [
                'movimiento_extracto_id' => $movimiento->id,
                'sugeridos' => $mismoValor->pluck('id')->values()->all(),
                'estado_sugerido' => match (true) {
                    $mismoValor->count() > 1 => 'duplicado',
                    $mismoValor->count() === 1 => 'exacto',
                    $enVentana->count() > 0 => 'con_diferencia',
                    default => 'sin_candidato',
                },
            ];
        });

        return [
            'extracto' => $extracto,
            'movimientos' => $movimientos->values()->all(),
            'asientos' => $asientos->values(),
            'sugerencias' => $sugerencias->values()->all(),
        ];
    }

    public function marcar(MovimientoExtracto $movimiento, int $asientoContableId, Usuario $usuario): MarcaConciliacion
    {
        return DB::transaction(function () use ($movimiento, $asientoContableId, $usuario) {
            $this->exigirPendiente($movimiento);

            if ($this->marcas->vigenteDeAsiento($asientoContableId) !== null) {
                throw ValidationException::withMessages(['asiento_contable_id' => __('validation.custom.conciliacion.asiento_ya_conciliado')]);
            }

            $asiento = AsientoContable::query()->findOrFail($asientoContableId);
            $valor = $this->valorAsiento($asiento);

            if (! $valor->igualA($movimiento->valor->absoluto())) {
                throw ValidationException::withMessages(['asiento_contable_id' => __('validation.custom.conciliacion.valor_no_coincide')]);
            }

            $marca = $this->marcas->crear([
                'movimiento_extracto_id' => $movimiento->id,
                'asiento_contable_id' => $asiento->id,
                'tipo' => TipoMarcaConciliacion::Conciliado,
                'valor' => $valor,
                'marcado_por' => $usuario->id,
            ]);

            $this->movimientos->actualizar($movimiento, ['estado' => EstadoMovimientoExtracto::Conciliado]);

            $this->auditoria->registrar(
                AccionAuditoria::Crear,
                AuditoriaConstants::MODULO_CONCILIACION,
                "Movimiento #{$movimiento->id} del extracto conciliado con el asiento #{$asiento->id}",
                'marca_conciliacion',
                $marca->id,
                nuevos: ['movimiento_extracto_id' => $movimiento->id, 'asiento_contable_id' => $asiento->id, 'valor' => $valor->aDecimalString()],
                autor: $usuario,
            );

            return $marca;
        });
    }

    public function enviarAPuente(MovimientoExtracto $movimiento, Usuario $usuario, int $tipoComprobanteId, ?string $observacion): MarcaConciliacion
    {
        return DB::transaction(function () use ($movimiento, $usuario, $tipoComprobanteId, $observacion) {
            $this->exigirPendiente($movimiento);

            $movimiento->loadMissing('extracto.cuentaBancaria');
            $cuenta = $movimiento->extracto->cuentaBancaria;

            if ($cuenta->plan_cuenta_puente_id === null) {
                throw ValidationException::withMessages(['movimiento' => __('validation.custom.conciliacion.sin_cuenta_puente')]);
            }

            $monto = $movimiento->valor->absoluto();
            // Abono en el extracto = entrada de dinero = débito en la cuenta del banco (cuenta de naturaleza débito).
            $esAbono = $movimiento->valor->esPositivo();

            $lineas = $esAbono
                ? [
                    ['cuenta_id' => $cuenta->plan_cuenta_id, 'debito' => $monto, 'credito' => 0],
                    ['cuenta_id' => $cuenta->plan_cuenta_puente_id, 'debito' => 0, 'credito' => $monto],
                ]
                : [
                    ['cuenta_id' => $cuenta->plan_cuenta_puente_id, 'debito' => $monto, 'credito' => 0],
                    ['cuenta_id' => $cuenta->plan_cuenta_id, 'debito' => 0, 'credito' => $monto],
                ];

            $comprobante = $this->comprobantes->crearComprobante(
                $tipoComprobanteId,
                $movimiento->fecha->toDateString(),
                $lineas,
                "Movimiento sin origen identificado: {$movimiento->descripcion}",
                'conciliacion_puente',
                $movimiento->id,
            );

            $marca = $this->marcas->crear([
                'movimiento_extracto_id' => $movimiento->id,
                'comprobante_generado_id' => $comprobante->id,
                'tipo' => TipoMarcaConciliacion::Puente,
                'valor' => $monto,
                'observacion' => $observacion,
                'marcado_por' => $usuario->id,
            ]);

            $this->movimientos->actualizar($movimiento, ['estado' => EstadoMovimientoExtracto::Puente]);

            $this->auditoria->registrar(
                AccionAuditoria::Crear,
                AuditoriaConstants::MODULO_CONCILIACION,
                "Movimiento #{$movimiento->id} del extracto enviado a cuenta puente",
                'marca_conciliacion',
                $marca->id,
                nuevos: ['movimiento_extracto_id' => $movimiento->id, 'comprobante_generado_id' => $comprobante->id, 'valor' => $monto->aDecimalString()],
                autor: $usuario,
            );

            return $marca;
        });
    }

    public function desmarcar(MarcaConciliacion $marca, Usuario $usuario, string $motivo): MarcaConciliacion
    {
        return DB::transaction(function () use ($marca, $usuario, $motivo) {
            if ($marca->tipo === TipoMarcaConciliacion::Reversa) {
                throw ValidationException::withMessages(['marca' => __('validation.custom.conciliacion.marca_no_reversable')]);
            }

            if ($this->marcas->existeReversaDe($marca->id)) {
                throw ValidationException::withMessages(['marca' => __('validation.custom.conciliacion.marca_ya_reversada')]);
            }

            if ($marca->tipo === TipoMarcaConciliacion::Puente) {
                $marca->loadMissing('comprobanteGenerado');
                $this->comprobantes->reversarComprobante($marca->comprobanteGenerado, $motivo);
            }

            $reversa = $this->marcas->crear([
                'movimiento_extracto_id' => $marca->movimiento_extracto_id,
                'tipo' => TipoMarcaConciliacion::Reversa,
                'marca_origen_id' => $marca->id,
                'valor' => $marca->valor,
                'observacion' => $motivo,
                'marcado_por' => $usuario->id,
            ]);

            $movimiento = $marca->movimientoExtracto;
            $this->movimientos->actualizar($movimiento, ['estado' => EstadoMovimientoExtracto::Pendiente]);

            $this->auditoria->registrar(
                AccionAuditoria::Reversar,
                AuditoriaConstants::MODULO_CONCILIACION,
                "Marca de conciliación #{$marca->id} del movimiento #{$marca->movimiento_extracto_id} desmarcada",
                'marca_conciliacion',
                $marca->id,
                nuevos: ['reversa_id' => $reversa->id],
                motivo: $motivo,
                autor: $usuario,
            );

            return $reversa;
        });
    }

    private function exigirPendiente(MovimientoExtracto $movimiento): void
    {
        if ($movimiento->estado !== EstadoMovimientoExtracto::Pendiente) {
            throw ValidationException::withMessages(['movimiento' => __('validation.custom.conciliacion.movimiento_no_pendiente')]);
        }
    }

    private function valorAsiento(AsientoContable $asiento): Dinero
    {
        return $asiento->debito->esPositivo() ? $asiento->debito : $asiento->credito;
    }
}
