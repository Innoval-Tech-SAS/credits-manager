<?php

namespace App\Contabilidad\Services;

use App\Contabilidad\Enums\EventoContable;
use App\Contabilidad\Models\Comprobante;
use App\Core\Dinero\Dinero;
use App\Creditos\Repositories\CuotaRepository;
use App\Creditos\Services\CuotaService;
use App\Usuarios\Repositories\UsuarioRepository;
use Illuminate\Support\Facades\Auth;

/**
 * M15: reconoce como ingreso el interés de cada cuota en el mes de su vencimiento (devengo), en vez de
 * esperar a que el cliente pague. El recaudo de interés debe quedar configurado para cancelar la cuenta
 * por cobrar que aquí se debita, no para volver a reconocer el ingreso — eso es una reconfiguración de la
 * regla de contabilización de `RecaudoInteres`, no algo que este servicio decida.
 */
class DevengoInteresService
{
    private const DIAS_MORA_SUSPENDE = 90;

    public function __construct(
        protected CuotaRepository $cuotas,
        protected CuotaService $cuotaService,
        protected ComprobanteService $comprobantes,
        protected UsuarioRepository $usuarios,
    ) {}

    /** @return array{comprobante: ?Comprobante, cuotas_devengadas: int, cuotas_suspendidas_por_mora: int} */
    public function devengarHasta(int $empresaId, string $hasta): array
    {
        $vacio = ['comprobante' => null, 'cuotas_devengadas' => 0, 'cuotas_suspendidas_por_mora' => 0];

        $usuario = $this->usuarios->primerActivoDeEmpresa($empresaId);

        if ($usuario === null) {
            return $vacio;
        }

        $anterior = Auth::user();
        Auth::setUser($usuario);

        try {
            // La regla vive por empresa (`PerteneceAEmpresa`): necesita Auth ya puesto para resolver la de esta empresa.
            if (! $this->comprobantes->eventosContablesActivos(EventoContable::DevengoInteres)) {
                return $vacio;
            }

            $candidatas = $this->cuotas->pendientesDeDevengoHasta($empresaId, $hasta);

            $aDevengar = [];
            $diasMoraPorCredito = [];
            $suspendidas = 0;

            foreach ($candidatas as $cuota) {
                $diasMoraPorCredito[$cuota->credito_id] ??= $this->cuotaService->diasMora($cuota->credito_id);

                if ($diasMoraPorCredito[$cuota->credito_id] >= self::DIAS_MORA_SUSPENDE) {
                    $suspendidas++;

                    continue;
                }

                $aDevengar[] = $cuota;
            }

            if ($aDevengar === []) {
                return [...$vacio, 'cuotas_suspendidas_por_mora' => $suspendidas];
            }

            $totalPorTercero = [];

            foreach ($aDevengar as $cuota) {
                $terceroId = $cuota->credito->cliente->tercero_id;
                $totalPorTercero[$terceroId] = ($totalPorTercero[$terceroId] ?? Dinero::cero())->sumar($cuota->valor_interes);
            }

            $comprobante = $this->comprobantes->contabilizarDevengoInteres($totalPorTercero, $hasta);

            $this->cuotas->marcarInteresDevengado(array_map(fn ($cuota) => $cuota->id, $aDevengar));

            return ['comprobante' => $comprobante, 'cuotas_devengadas' => count($aDevengar), 'cuotas_suspendidas_por_mora' => $suspendidas];
        } finally {
            $anterior ? Auth::setUser($anterior) : Auth::forgetUser();
        }
    }
}
