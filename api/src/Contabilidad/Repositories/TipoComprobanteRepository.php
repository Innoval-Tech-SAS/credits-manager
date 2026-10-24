<?php

namespace App\Contabilidad\Repositories;

use App\Causacion\Models\ParametrizacionTipoGasto;
use App\Contabilidad\Enums\ReinicioConsecutivo;
use App\Contabilidad\Enums\TipoNumeracionComprobante;
use App\Contabilidad\Models\Comprobante;
use App\Contabilidad\Models\ReglaContabilizacion;
use App\Contabilidad\Models\TipoComprobante;
use App\Core\Base\BaseRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class TipoComprobanteRepository extends BaseRepository
{
    public function __construct(TipoComprobante $tipoComprobante)
    {
        parent::__construct($tipoComprobante);
    }

    public function paginar(?string $busqueda, int $porPagina, int $pagina): LengthAwarePaginator
    {
        $consulta = $this->consulta()->orderBy('codigo');

        if ($busqueda) {
            $consulta->where(function (Builder $subconsulta) use ($busqueda) {
                $subconsulta->where('codigo', 'like', "%{$busqueda}%")
                    ->orWhere('nombre', 'like', "%{$busqueda}%");
            });
        }

        return $consulta->paginate(perPage: $porPagina, page: $pagina);
    }

    /** Regla de negocio "sin movimientos": el tipo ya tiene comprobantes contabilizados con ese número. */
    public function tieneComprobantesAsociados(int $tipoComprobanteId): bool
    {
        return Comprobante::where('tipo_comprobante_id', $tipoComprobanteId)->exists();
    }

    /** Evita que el `DELETE` reviente por el FK `RESTRICT` de reglas/parametrizaciones que usan este tipo. */
    public function estaEnUsoPorConfiguracion(int $tipoComprobanteId): bool
    {
        return ReglaContabilizacion::where('tipo_comprobante_id', $tipoComprobanteId)->exists()
            || ParametrizacionTipoGasto::where('tipo_comprobante_id', $tipoComprobanteId)->exists();
    }

    /**
     * Asigna el consecutivo de un comprobante nuevo con `lockForUpdate()`. A diferencia del diseño anterior
     * (que incrementaba en su propia transacción corta, ANTES de crear el comprobante, aceptando huecos si
     * la creación fallaba después), este método asume que el llamador ya abrió la transacción del
     * comprobante: así, si algo falla después, el `ROLLBACK` también revierte el consecutivo — sin huecos.
     *
     * El ciclo (reinicio anual/mensual) sale de la fecha del comprobante, no de hoy: un comprobante de 2024
     * cargado en 2026 se numera en el ciclo 2024. `consecutivo_actual` solo lleva el ciclo más reciente; un
     * ciclo anterior continúa desde el mayor consecutivo que ya tenga, sin mover el contador del vigente.
     * `$origenManual` solo lo manda `ComprobanteController::store` (comprobante manual); los flujos
     * automáticos (créditos, pagos, importación, reverso) siempre auto-incrementan, sin importar cómo esté
     * configurado `tipo_numeracion` en el tipo — así un tipo mal configurado como "manual" nunca rompe un
     * flujo de negocio automático.
     *
     * @return array{consecutivo: int, ciclo_numeracion: string}
     */
    public function asignarConsecutivo(int $tipoComprobanteId, string $fecha, bool $origenManual, ?int $consecutivoManual = null): array
    {
        $tipo = $this->consulta()->whereKey($tipoComprobanteId)->lockForUpdate()->firstOrFail();
        $ciclo = $this->calcularCiclo($tipo->reinicio_periodo, CarbonImmutable::parse($fecha));

        if ($ciclo > $tipo->ciclo_numeracion_actual) {
            $tipo->consecutivo_actual = max($tipo->consecutivo_inicial - 1, $this->mayorConsecutivoDelCiclo($tipo->id, $ciclo));
            $tipo->ciclo_numeracion_actual = $ciclo;
        }

        $esCicloVigente = $tipo->ciclo_numeracion_actual === $ciclo;
        $ultimoDelCiclo = $esCicloVigente
            ? $tipo->consecutivo_actual
            : max($tipo->consecutivo_inicial - 1, $this->mayorConsecutivoDelCiclo($tipo->id, $ciclo));

        if ($origenManual && $tipo->tipo_numeracion === TipoNumeracionComprobante::Manual) {
            $consecutivo = $this->validarConsecutivoManual($tipo, $consecutivoManual, $ciclo);
        } else {
            $consecutivo = $ultimoDelCiclo + 1;
            $this->exigirDentroDelRango($tipo, $consecutivo);
        }

        if ($esCicloVigente && $consecutivo > $tipo->consecutivo_actual) {
            $tipo->consecutivo_actual = $consecutivo;
        }

        $tipo->save();

        return ['consecutivo' => $consecutivo, 'ciclo_numeracion' => $ciclo, 'codigo' => $tipo->codigo];
    }

    private function calcularCiclo(ReinicioConsecutivo $reinicio, CarbonImmutable $fecha): string
    {
        return match ($reinicio) {
            ReinicioConsecutivo::Nunca => '',
            ReinicioConsecutivo::Anual => (string) $fecha->year,
            ReinicioConsecutivo::Mensual => $fecha->format('Y-m'),
        };
    }

    /** Incluye borrados: el índice único `(tipo, ciclo, consecutivo)` también los cuenta. */
    private function mayorConsecutivoDelCiclo(int $tipoComprobanteId, string $ciclo): int
    {
        return (int) Comprobante::withTrashed()
            ->where('tipo_comprobante_id', $tipoComprobanteId)
            ->where('ciclo_numeracion', $ciclo)
            ->max('consecutivo');
    }

    private function exigirDentroDelRango(TipoComprobante $tipo, int $consecutivo): void
    {
        if ($tipo->consecutivo_final !== null && $consecutivo > $tipo->consecutivo_final) {
            throw ValidationException::withMessages([
                'tipo_comprobante' => __('validation.custom.tipo_comprobante.rango_agotado'),
            ]);
        }
    }

    private function validarConsecutivoManual(TipoComprobante $tipo, ?int $consecutivo, string $ciclo): int
    {
        if ($consecutivo === null) {
            throw ValidationException::withMessages([
                'consecutivo' => __('validation.custom.tipo_comprobante.consecutivo_requerido'),
            ]);
        }

        if ($consecutivo < $tipo->consecutivo_inicial || ($tipo->consecutivo_final !== null && $consecutivo > $tipo->consecutivo_final)) {
            throw ValidationException::withMessages([
                'consecutivo' => __('validation.custom.tipo_comprobante.consecutivo_fuera_de_rango'),
            ]);
        }

        if (Comprobante::withTrashed()->where('tipo_comprobante_id', $tipo->id)->where('ciclo_numeracion', $ciclo)->where('consecutivo', $consecutivo)->exists()) {
            throw ValidationException::withMessages([
                'consecutivo' => __('validation.custom.tipo_comprobante.consecutivo_duplicado'),
            ]);
        }

        return $consecutivo;
    }
}
