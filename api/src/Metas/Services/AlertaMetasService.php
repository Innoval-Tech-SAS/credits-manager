<?php

namespace App\Metas\Services;

use App\Metas\Constants\MetasConstants;
use App\Metas\Data\Cumplimiento;
use App\Metas\Enums\EstadoMeta;
use App\Metas\Models\AlertaMeta;
use App\Metas\Models\Meta;
use App\Metas\Repositories\AlertaMetaRepository;
use App\Usuarios\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AlertaMetasService
{
    public function __construct(protected AlertaMetaRepository $alertas) {}

    /**
     * Genera una alerta por cada estado distinto de «en curso», para la empresa y para cada asesor.
     * Repetir la evaluación no duplica nada: la unicidad de la tabla lo impide.
     *
     * @param  list<array{meta: Meta, cerrado: bool, empresa: Cumplimiento, asesores: list<array{usuario_id: int, nombre: string, cumplimiento: Cumplimiento}>}>  $resumen
     */
    public function generar(int $empresaId, array $resumen, CarbonImmutable $ahora): void
    {
        $generadaEn = $ahora->setTimezone(MetasConstants::ZONA_HORARIA)->toDateTimeString();
        $filas = [];

        foreach ($resumen as $item) {
            $filas[] = $this->fila($empresaId, $item['meta'], null, $item['empresa'], $generadaEn);

            foreach ($item['asesores'] as $asesor) {
                $filas[] = $this->fila($empresaId, $item['meta'], $asesor['usuario_id'], $asesor['cumplimiento'], $generadaEn);
            }
        }

        $this->alertas->insertarSiNoExisten(array_values(array_filter($filas)));
    }

    /** @return Collection<int, AlertaMeta> */
    public function listar(Usuario $usuario, bool $verTodo): Collection
    {
        return $this->alertas->visiblesPara((int) $usuario->empresa_id, $verTodo ? null : $usuario->id);
    }

    public function marcarVista(Usuario $usuario, AlertaMeta $alerta, bool $verTodo): AlertaMeta
    {
        if (! $verTodo && $alerta->usuario_id !== null && (int) $alerta->usuario_id !== $usuario->id) {
            throw (new ModelNotFoundException)->setModel(AlertaMeta::class, [$alerta->id]);
        }

        if ($alerta->vista_en === null) {
            $alerta->forceFill(['vista_en' => CarbonImmutable::now(MetasConstants::ZONA_HORARIA)])->save();
        }

        return $alerta;
    }

    /** @return array<string, mixed>|null null cuando el estado no merece alerta */
    private function fila(int $empresaId, Meta $meta, ?int $usuarioId, Cumplimiento $cumplimiento, string $generadaEn): ?array
    {
        if ($cumplimiento->estado === EstadoMeta::EnCurso) {
            return null;
        }

        return [
            'empresa_id' => $empresaId,
            'meta_id' => $meta->id,
            'usuario_id' => $usuarioId,
            'periodo' => $meta->periodo,
            'estado' => $cumplimiento->estado->value,
            'generada_en' => $generadaEn,
        ];
    }
}
