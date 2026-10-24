<?php

namespace App\ObligacionesTributarias\Services;

use App\Notificaciones\Enums\TipoNotificacion;
use App\Notificaciones\Services\NotificacionService;
use App\ObligacionesTributarias\Models\AlertaObligacionTributaria;
use App\ObligacionesTributarias\Models\ObligacionTributaria;
use App\ObligacionesTributarias\Repositories\AlertaObligacionTributariaRepository;
use App\ObligacionesTributarias\Repositories\ObligacionTributariaRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class AlertaObligacionTributariaService
{
    public function __construct(
        protected ObligacionTributariaRepository $obligaciones,
        protected AlertaObligacionTributariaRepository $alertas,
        protected NotificacionService $notificaciones,
    ) {}

    /** Genera una alerta por cada obligación pendiente de la empresa que entra en su ventana. No duplica: cada obligación recibe una sola alerta. */
    public function generarParaEmpresa(int $empresaId, CarbonImmutable $hoy): int
    {
        $generadas = 0;

        foreach ($this->obligaciones->pendientesDeEmpresa($empresaId) as $obligacion) {
            if ($this->alertas->existeParaObligacion($obligacion->id)) {
                continue;
            }

            $diasAlertaPrevia = $obligacion->dias_alerta_previa ?? $obligacion->tipoObligacion->dias_alerta_previa;
            $diasRestantes = $hoy->startOfDay()->diffInDays($obligacion->fecha_limite->startOfDay(), false);

            if ($diasRestantes > $diasAlertaPrevia) {
                continue;
            }

            $this->generarAlerta($obligacion, $diasRestantes, $hoy);
            $generadas++;
        }

        return $generadas;
    }

    public function listar(int $empresaId): Collection
    {
        return $this->alertas->deEmpresa($empresaId);
    }

    public function marcarVista(AlertaObligacionTributaria $alerta): AlertaObligacionTributaria
    {
        return $this->alertas->marcarVista($alerta);
    }

    private function generarAlerta(ObligacionTributaria $obligacion, int $diasRestantes, CarbonImmutable $hoy): void
    {
        $notificacionId = null;

        if ($obligacion->responsable_id !== null) {
            $notificacion = $this->notificaciones->notificar(
                (int) $obligacion->responsable_id,
                (int) $obligacion->empresa_id,
                TipoNotificacion::ObligacionTributariaPorVencer,
                'Obligación tributaria por vencer',
                $this->mensaje($obligacion, $diasRestantes),
                ['obligacion_id' => $obligacion->id],
            );

            $notificacionId = $notificacion->id;
        }

        $this->alertas->insertarSiNoExiste([
            'empresa_id' => $obligacion->empresa_id,
            'obligacion_id' => $obligacion->id,
            'fecha_limite' => $obligacion->fecha_limite,
            'dias_restantes' => $diasRestantes,
            'generada_en' => $hoy,
            'notificacion_id' => $notificacionId,
        ]);
    }

    private function mensaje(ObligacionTributaria $obligacion, int $diasRestantes): string
    {
        $nombreTipo = $obligacion->tipoObligacion->nombre;

        if ($diasRestantes < 0) {
            return "La obligación «{$nombreTipo}» del período {$obligacion->periodo} venció hace ".abs($diasRestantes).' día(s).';
        }

        if ($diasRestantes === 0) {
            return "La obligación «{$nombreTipo}» del período {$obligacion->periodo} vence hoy.";
        }

        return "La obligación «{$nombreTipo}» del período {$obligacion->periodo} vence en {$diasRestantes} día(s).";
    }
}
