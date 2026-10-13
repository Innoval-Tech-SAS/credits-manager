<?php

namespace App\Auditoria\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Data\EventoAuditoria;
use App\Auditoria\Enums\MotivoRespaldo;
use App\Auditoria\Repositories\BitacoraAuditoriaRepository;
use App\Monitoreo\Constants\MonitoreoConstants;
use App\Monitoreo\Enums\EstadoServicio;
use App\Monitoreo\Services\RegistroLatidos;
use App\Redis\Contracts\RedisClienteInterface;
use App\Redis\Exceptions\RedisClienteException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Consumidor del stream de auditoría (consumer group + XREADGROUP BLOCK): el evento llega en el
 * instante del XADD, sin polling. Cada entrada se inserta (idempotente por evento_id) y solo
 * entonces se confirma con XACK. Una entrada entregada a un consumidor que muere antes de
 * confirmar queda pendiente en el grupo y se reclama (XAUTOCLAIM), sin perderse.
 * Corre sin usuario autenticado: el `empresa_id` viaja en el evento, nunca se infiere de Auth.
 */
class EscuchaAuditoria
{
    private bool $detener = false;

    private float $esperaInicial = 1.0;

    private float $ultimaReclamacion = 0.0;

    public function __construct(
        protected RedisClienteInterface $redis,
        protected BitacoraAuditoriaRepository $bitacora,
        protected RespaldoAuditoriaService $respaldo,
        protected DetectorConexionPerdida $conexionPerdida,
        protected ?RegistroLatidos $latidos = null,
    ) {}

    public function solicitarParada(): void
    {
        $this->detener = true;
    }

    public function conEsperaInicial(float $segundos): static
    {
        $this->esperaInicial = $segundos;

        return $this;
    }

    public function iniciar(string $consumidor): void
    {
        $this->detener = false;

        $this->prepararGrupo();

        Log::info('Listener de auditoría iniciado.', [
            'consumidor' => $consumidor,
            'en_stream' => $this->intentar(fn () => $this->redis->longitud(AuditoriaConstants::STREAM)),
            'pendientes' => $this->intentar(fn () => $this->redis->pendientes(AuditoriaConstants::STREAM, AuditoriaConstants::GRUPO)),
        ]);

        $espera = $this->esperaInicial;

        while (! $this->detener) {
            $this->latidos?->registrar(MonitoreoConstants::SERVICIO_LISTENER, EstadoServicio::Activo);

            try {
                $this->ciclo($consumidor, AuditoriaConstants::BLOQUEO_MS);
                $espera = $this->esperaInicial;
            } catch (RedisClienteException $excepcion) {
                Log::error('Listener de auditoría: Redis no responde, se reintenta.', ['error' => $excepcion->getMessage()]);

                $this->dormir($espera);
                $espera = min($espera * 2, AuditoriaConstants::ESPERA_MAXIMA_SEGUNDOS);

                $this->intentar(fn () => $this->redis->crearGrupo(AuditoriaConstants::STREAM, AuditoriaConstants::GRUPO));
            }
        }

        Log::info('Listener de auditoría detenido.', ['consumidor' => $consumidor]);
    }

    /**
     * Una vuelta del listener: reclama huérfanos cuando corresponde y procesa las entradas nuevas.
     * El BLOCK solo acota la espera para poder revisar SIGTERM; si hay entradas, retorna al instante.
     *
     * @return int entradas procesadas
     */
    public function ciclo(string $consumidor, int $bloqueoMs): int
    {
        $procesadas = 0;

        if (microtime(true) - $this->ultimaReclamacion >= AuditoriaConstants::RECLAMAR_INACTIVAS_MS / 1000) {
            $this->ultimaReclamacion = microtime(true);

            $procesadas += $this->procesarLote(
                $this->redis->reclamarInactivas(AuditoriaConstants::STREAM, AuditoriaConstants::GRUPO, $consumidor, AuditoriaConstants::RECLAMAR_INACTIVAS_MS, AuditoriaConstants::LOTE),
            );
        }

        return $procesadas + $this->procesarLote(
            $this->redis->leerNuevas(AuditoriaConstants::STREAM, AuditoriaConstants::GRUPO, $consumidor, AuditoriaConstants::LOTE, $bloqueoMs),
        );
    }

    /** @param array<string, array<string, string>> $entradas */
    private function procesarLote(array $entradas): int
    {
        $procesadas = 0;

        foreach ($entradas as $id => $campos) {
            if ($this->detener) {
                break;
            }

            if ($this->procesarEntrada((string) $id, $campos)) {
                $procesadas++;
            }
        }

        return $procesadas;
    }

    /** @return bool false si quedó pendiente porque se pidió detener durante una caída de la BD */
    private function procesarEntrada(string $id, array $campos): bool
    {
        try {
            $evento = EventoAuditoria::desdeArray(json_decode((string) ($campos['payload'] ?? ''), true, 512, JSON_THROW_ON_ERROR));
        } catch (Throwable $excepcion) {
            $this->respaldo->guardar(MotivoRespaldo::PayloadInvalido, null, $excepcion->getMessage(), ['id_stream' => $id, 'evento_id' => $campos['evento_id'] ?? null, 'campos' => $campos]);
            $this->redis->confirmar(AuditoriaConstants::STREAM, AuditoriaConstants::GRUPO, [$id]);

            return true;
        }

        try {
            if (! $this->insertarConReintentos($evento)) {
                return false;
            }
        } catch (Throwable $excepcion) {
            $this->respaldo->guardar(MotivoRespaldo::ErrorInsercion, $evento, $excepcion->getMessage());
        }

        $this->redis->confirmar(AuditoriaConstants::STREAM, AuditoriaConstants::GRUPO, [$id]);

        return true;
    }

    /**
     * Caída de la BD: reintenta sin límite (con backoff y reconexión) sin confirmar la entrada.
     * Error persistente de otro tipo: lanza tras INTENTOS_INSERCION para que pase al log de respaldo.
     *
     * @return bool false si se pidió detener mientras la BD seguía caída
     */
    private function insertarConReintentos(EventoAuditoria $evento): bool
    {
        $intentos = 0;
        $espera = $this->esperaInicial;

        while (true) {
            try {
                $this->bitacora->insertarSiNoExiste($evento);

                return true;
            } catch (Throwable $excepcion) {
                if ($this->conexionPerdida->detecta($excepcion)) {
                    if ($this->detener) {
                        return false;
                    }

                    Log::warning('Listener de auditoría: base de datos no disponible, se reintenta.', ['evento_id' => $evento->eventoId, 'error' => $excepcion->getMessage()]);

                    DB::purge();
                    $this->dormir($espera);
                    $espera = min($espera * 2, AuditoriaConstants::ESPERA_MAXIMA_SEGUNDOS);

                    continue;
                }

                if (++$intentos >= AuditoriaConstants::INTENTOS_INSERCION) {
                    throw $excepcion;
                }

                $this->dormir($this->esperaInicial);
            }
        }
    }

    private function prepararGrupo(): void
    {
        $espera = $this->esperaInicial;

        while (! $this->detener) {
            try {
                $this->redis->crearGrupo(AuditoriaConstants::STREAM, AuditoriaConstants::GRUPO);

                return;
            } catch (RedisClienteException $excepcion) {
                Log::error('Listener de auditoría: Redis no disponible al arrancar.', ['error' => $excepcion->getMessage()]);

                $this->dormir($espera);
                $espera = min($espera * 2, AuditoriaConstants::ESPERA_MAXIMA_SEGUNDOS);
            }
        }
    }

    private function intentar(callable $operacion): mixed
    {
        try {
            return $operacion();
        } catch (Throwable) {
            return null;
        }
    }

    /** Espera en tramos cortos para reaccionar a SIGTERM sin quedarse dormido. */
    private function dormir(float $segundos): void
    {
        $hasta = microtime(true) + $segundos;

        while (! $this->detener && microtime(true) < $hasta) {
            usleep(100_000);
        }
    }
}
