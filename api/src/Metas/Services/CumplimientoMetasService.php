<?php

namespace App\Metas\Services;

use App\Core\Dinero\Dinero;
use App\Metas\Data\Cumplimiento;
use App\Metas\Data\ParametrosMetas;
use App\Metas\Data\PeriodoMeta;
use App\Metas\Models\Meta;
use App\Metas\Models\MetaCierre;
use App\Metas\Repositories\CierreMetaRepository;
use App\Usuarios\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Cumplimiento de cada meta. Una meta con cierre vigente muestra su cierre tal cual quedó (congelado); las demás,
 * el cálculo en vivo. Lo calcula para toda la empresa de una vez, así que no depende de quién consulta.
 */
class CumplimientoMetasService
{
    public function __construct(
        protected EjecucionMetasService $ejecucion,
        protected CierreMetaRepository $cierres,
    ) {}

    /**
     * @param  Collection<int, Meta>  $metas  con `asesores` cargados
     * @return list<array{meta: Meta, cerrado: bool, empresa: Cumplimiento, asesores: list<array{usuario_id: int, nombre: string, cumplimiento: Cumplimiento}>}>
     */
    public function resumen(int $empresaId, Collection $metas, CarbonImmutable $hoy, ParametrosMetas $parametros): array
    {
        $vigentes = $this->cierres->vigentesDeMetas($empresaId, $metas->pluck('id')->map(fn ($id) => (int) $id)->all());
        $nombres = $this->nombresDeAsesores($empresaId, $metas, $vigentes);
        $calculos = [];
        $resumen = [];

        foreach ($metas as $meta) {
            $cierresDeMeta = $vigentes->where('meta_id', $meta->id);

            $resumen[] = $cierresDeMeta->contains(fn (MetaCierre $cierre) => $cierre->asesor_id === null)
                ? $this->desdeCierres($meta, $cierresDeMeta, $nombres)
                : $this->enVivo($empresaId, $meta, $hoy, $parametros, $calculos, $nombres);
        }

        return $resumen;
    }

    /** @param array<string, array{total: Dinero, porAsesor: array<int, Dinero>}> $calculos cache por período y tipo */
    private function enVivo(int $empresaId, Meta $meta, CarbonImmutable $hoy, ParametrosMetas $parametros, array &$calculos, array $nombres): array
    {
        $periodo = PeriodoMeta::desde($meta->periodo);
        $clave = $meta->periodo.'|'.$meta->tipo->value;
        $calculos[$clave] ??= $this->ejecucion->calcular($empresaId, $periodo, $meta->tipo, $parametros);
        $ejecutado = $calculos[$clave];
        $centesimasDeDias = $periodo->centesimasDeDiasTranscurridos($hoy);

        return [
            'meta' => $meta,
            'cerrado' => false,
            'empresa' => Cumplimiento::calcular($meta->valor_objetivo, $ejecutado['total'], false, $parametros, $centesimasDeDias),
            'asesores' => $meta->asesores->map(fn ($fila) => [
                'usuario_id' => (int) $fila->usuario_id,
                'nombre' => $nombres[$fila->usuario_id] ?? '',
                'cumplimiento' => Cumplimiento::calcular(
                    $fila->valor_objetivo,
                    $ejecutado['porAsesor'][$fila->usuario_id] ?? Dinero::cero(),
                    false,
                    $parametros,
                    $centesimasDeDias,
                ),
            ])->values()->all(),
        ];
    }

    private function desdeCierres(Meta $meta, Collection $cierres, array $nombres): array
    {
        $deEmpresa = $cierres->first(fn (MetaCierre $cierre) => $cierre->asesor_id === null);

        return [
            'meta' => $meta,
            'cerrado' => true,
            'empresa' => $this->cumplimientoDeCierre($deEmpresa),
            'asesores' => $cierres
                ->filter(fn (MetaCierre $cierre) => $cierre->asesor_id !== null)
                ->map(fn (MetaCierre $cierre) => [
                    'usuario_id' => (int) $cierre->asesor_id,
                    'nombre' => $nombres[$cierre->asesor_id] ?? '',
                    'cumplimiento' => $this->cumplimientoDeCierre($cierre),
                ])
                ->values()
                ->all(),
        ];
    }

    private function cumplimientoDeCierre(MetaCierre $cierre): Cumplimiento
    {
        return Cumplimiento::desdeCierre($cierre->objetivo, $cierre->ejecutado, $cierre->porcentaje, $cierre->estado);
    }

    /** @return array<int, string> */
    private function nombresDeAsesores(int $empresaId, Collection $metas, Collection $cierres): array
    {
        $ids = $metas->flatMap(fn (Meta $meta) => $meta->asesores->pluck('usuario_id'))
            ->merge($cierres->pluck('asesor_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        return Usuario::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('id', $ids)
            ->pluck('nombre', 'id')
            ->all();
    }
}
