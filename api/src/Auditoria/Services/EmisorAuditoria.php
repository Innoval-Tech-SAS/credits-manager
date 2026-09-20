<?php

namespace App\Auditoria\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Data\AutorAuditoria;
use App\Auditoria\Data\EventoAuditoria;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Enums\MotivoRespaldo;
use App\Redis\Contracts\RedisClienteInterface;
use App\Usuarios\Models\Usuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Único punto por el que los servicios de negocio registran auditoría. Nunca inserta en la base
 * de datos ni interrumpe la operación de negocio: publica en el stream de Redis (XADD) y, si eso
 * falla, deja el evento completo en el log de respaldo. Uso desde un Service, en una línea:
 *
 *   $this->auditoria->registrar(AccionAuditoria::Crear, 'clientes', "Cliente {$c->nombre} creado", 'cliente', $c->id, nuevos: $c->toArray());
 */
class EmisorAuditoria
{
    public function __construct(
        protected RedisClienteInterface $redis,
        protected RespaldoAuditoriaService $respaldo,
    ) {}

    public function registrar(
        AccionAuditoria $accion,
        string $modulo,
        string $descripcion,
        ?string $entidad = null,
        ?int $entidadId = null,
        ?array $anteriores = null,
        ?array $nuevos = null,
        ?string $motivo = null,
        ?Usuario $autor = null,
    ): void {
        try {
            $autor ??= Auth::user();

            $evento = EventoAuditoria::crear(
                accion: $accion,
                modulo: $modulo,
                descripcion: $descripcion,
                autor: AutorAuditoria::desdeUsuario($autor),
                empresaId: $autor?->empresa_id,
                ip: request()->ip(),
                entidad: $entidad,
                entidadId: $entidadId,
                valoresAnteriores: $anteriores,
                valoresNuevos: $nuevos,
                motivo: $motivo,
            );
        } catch (Throwable $excepcion) {
            $this->respaldo->guardar(MotivoRespaldo::EventoInvalido, null, $excepcion->getMessage(), [
                'accion' => $accion->value,
                'modulo' => $modulo,
                'descripcion' => $descripcion,
                'entidad' => $entidad,
                'entidad_id' => $entidadId,
                'usuario_id' => $autor?->id,
            ]);

            return;
        }

        // Dentro de una transacción de negocio el evento se entrega al confirmarse: un rollback no deja rastro de algo que no ocurrió.
        DB::afterCommit(fn () => $this->emitir($evento));
    }

    /** @return bool false si Redis falló y el evento quedó en el log de respaldo. */
    public function emitir(EventoAuditoria $evento): bool
    {
        try {
            $this->publicar($evento);

            return true;
        } catch (Throwable $excepcion) {
            $this->respaldo->guardar(MotivoRespaldo::RedisNoDisponible, $evento, $excepcion->getMessage());

            return false;
        }
    }

    /** Publica sin respaldo: la usa la recuperación, que debe saber si Redis sigue caído. */
    public function publicar(EventoAuditoria $evento): void
    {
        $this->redis->agregar(AuditoriaConstants::STREAM, [
            'evento_id' => $evento->eventoId,
            'payload' => $evento->aJson(),
        ]);
    }
}
