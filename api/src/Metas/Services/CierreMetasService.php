<?php

namespace App\Metas\Services;

use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Core\Dinero\Dinero;
use App\Metas\Constants\MetasConstants;
use App\Metas\Data\Cumplimiento;
use App\Metas\Data\ParametrosMetas;
use App\Metas\Data\PeriodoMeta;
use App\Metas\Enums\TipoMeta;
use App\Metas\Models\Meta;
use App\Metas\Models\MetaCierre;
use App\Metas\Repositories\CierreMetaRepository;
use App\Metas\Repositories\MetaRepository;
use App\Usuarios\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CierreMetasService
{
    public function __construct(
        protected MetaRepository $metas,
        protected CierreMetaRepository $cierres,
        protected EjecucionMetasService $ejecucion,
        protected ConfiguracionMetasService $configuracion,
        protected EmisorAuditoria $auditoria,
    ) {}

    /**
     * Cierra las metas cuyo día de cierre ya llegó y que todavía no tienen cierre. Sin sesión: `empresaId` explícito.
     * Devuelve cuántas metas cerró en esta corrida.
     */
    public function cerrarVencidas(int $empresaId, CarbonImmutable $hoy): int
    {
        $parametros = $this->configuracion->parametros($empresaId);
        $cerradas = $this->cierres->metaIdsCerradas($empresaId);
        $cerradasAhora = 0;

        foreach ($this->metas->deEmpresa($empresaId) as $meta) {
            if (in_array($meta->id, $cerradas, true)) {
                continue;
            }

            $periodo = PeriodoMeta::desde($meta->periodo);

            if ($hoy->startOfDay() < $periodo->fechaDeCierre($parametros->diaDeCierre)) {
                continue;
            }

            $cerradasAhora += DB::transaction(fn () => $this->cerrarMeta($empresaId, $meta, $periodo, $parametros, $hoy) ? 1 : 0);
        }

        return $cerradasAhora;
    }

    /**
     * Recalcula un período ya cerrado con los datos y parámetros actuales. No edita el cierre anterior: inserta uno
     * nuevo que lo referencia, con el motivo y la bitácora. Es la única forma de cambiar un período cerrado.
     *
     * @return list<array<string, mixed>>
     */
    public function recalcular(Usuario $usuario, PeriodoMeta $periodo, TipoMeta $tipo, string $motivo): array
    {
        $empresaId = (int) $usuario->empresa_id;

        return DB::transaction(function () use ($usuario, $empresaId, $periodo, $tipo, $motivo) {
            $meta = $this->metas->delPeriodoYTipo($empresaId, $periodo, $tipo);

            if ($meta === null) {
                throw ValidationException::withMessages(['tipo' => __('validation.custom.metas.sin_meta')]);
            }

            $meta = $this->metas->bloquear($meta);
            $vigentes = $this->cierres->vigentesDeMetas($empresaId, [$meta->id]);

            if ($vigentes->isEmpty()) {
                throw ValidationException::withMessages(['periodo' => __('validation.custom.metas.periodo_no_cerrado')]);
            }

            $meta->load('asesores');
            $anteriores = $this->resumenDeCierres($vigentes)->all();
            $nuevos = $this->escribirCierre($empresaId, $meta, $periodo, $this->configuracion->parametros($empresaId), CarbonImmutable::now(MetasConstants::ZONA_HORARIA), $usuario->id, $motivo, $vigentes);
            $resumenNuevo = $this->resumenDeCierres(collect($nuevos))->all();

            $this->auditoria->registrar(
                AccionAuditoria::Recalcular,
                MetasConstants::MODULO,
                "Período {$periodo->codigo()} de {$tipo->value} recalculado",
                'meta',
                $meta->id,
                $anteriores,
                $resumenNuevo,
                $motivo,
                $usuario,
            );

            return $resumenNuevo;
        });
    }

    /** Cierre automático: una transacción por meta; si otra corrida ya la cerró, no hace nada. */
    private function cerrarMeta(int $empresaId, Meta $meta, PeriodoMeta $periodo, ParametrosMetas $parametros, CarbonImmutable $hoy): bool
    {
        $meta = $this->metas->bloquear($meta);

        if ($this->cierres->tieneCierres($empresaId, $meta->id)) {
            return false;
        }

        $meta->load('asesores');
        $cierres = $this->escribirCierre($empresaId, $meta, $periodo, $parametros, $hoy->startOfDay(), null, null, collect());

        $this->auditoria->registrar(
            AccionAuditoria::CerrarPeriodo,
            MetasConstants::MODULO,
            "Período {$periodo->codigo()} de {$meta->tipo->value} cerrado",
            'meta',
            $meta->id,
            null,
            $this->resumenDeCierres(collect($cierres))->all(),
            null,
            null,
            $empresaId,
        );

        return true;
    }

    /**
     * Inserta un cierre por cada fila de cumplimiento (empresa y cada asesor distribuido). Los `$vigentes` previos
     * se enlazan por `cierre_anterior_id`.
     *
     * @return list<MetaCierre>
     */
    private function escribirCierre(
        int $empresaId,
        Meta $meta,
        PeriodoMeta $periodo,
        ParametrosMetas $parametros,
        CarbonImmutable $cerradoEn,
        ?int $usuarioId,
        ?string $motivo,
        Collection $vigentes,
    ): array {
        $ejecutado = $this->ejecucion->calcular($empresaId, $periodo, $meta->tipo, $parametros);

        $filas = [[
            'asesor_id' => null,
            'cumplimiento' => Cumplimiento::calcular($meta->valor_objetivo, $ejecutado['total'], true, $parametros, 0),
        ]];

        foreach ($meta->asesores as $fila) {
            $filas[] = [
                'asesor_id' => (int) $fila->usuario_id,
                'cumplimiento' => Cumplimiento::calcular($fila->valor_objetivo, $ejecutado['porAsesor'][$fila->usuario_id] ?? Dinero::cero(), true, $parametros, 0),
            ];
        }

        return array_map(function (array $fila) use ($empresaId, $meta, $periodo, $cerradoEn, $usuarioId, $motivo, $vigentes) {
            $anterior = $vigentes->first(fn (MetaCierre $cierre) => $cierre->asesor_id == $fila['asesor_id']);

            return $this->cierres->crear([
                'empresa_id' => $empresaId,
                'meta_id' => $meta->id,
                'periodo' => $periodo->codigo(),
                'tipo' => $meta->tipo->value,
                'asesor_id' => $fila['asesor_id'],
                'usuario_id' => $usuarioId,
                'cierre_anterior_id' => $anterior?->id,
                'objetivo' => $fila['cumplimiento']->objetivo,
                'ejecutado' => $fila['cumplimiento']->ejecutado,
                'porcentaje' => $fila['cumplimiento']->porcentaje(),
                'estado' => $fila['cumplimiento']->estado->value,
                'motivo' => $motivo,
                'cerrado_en' => $cerradoEn,
            ]);
        }, $filas);
    }

    /** Foto compacta para la bitácora: por asesor (o `empresa`), objetivo, ejecutado y estado. */
    private function resumenDeCierres(Collection $cierres): Collection
    {
        return $cierres->mapWithKeys(fn ($cierre) => [
            ($cierre->asesor_id ?? 'empresa') => [
                'objetivo' => $cierre->objetivo->aDecimalString(),
                'ejecutado' => $cierre->ejecutado->aDecimalString(),
                'estado' => $cierre->estado->value,
            ],
        ]);
    }
}
