<?php

namespace App\Auditoria\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Data\AutorAuditoria;
use App\Auditoria\Data\EventoAuditoria;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Enums\MotivoRespaldo;
use App\Redis\Contracts\RedisClienteInterface;
use App\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
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
        ?int $empresaId = null,
    ): void {
        try {
            $autor ??= Auth::user();

            $evento = EventoAuditoria::crear(
                accion: $accion,
                modulo: $modulo,
                descripcion: $descripcion,
                autor: AutorAuditoria::desdeUsuario($autor),
                empresaId: $empresaId ?? $autor?->empresa_id,
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

    /** Foto de los atributos de un modelo sin columnas de tiempo: se toma antes de modificar y se pasa a `registrarModificacion`. */
    public function instantanea(Model $modelo): array
    {
        return Arr::except($modelo->attributesToArray(), AuditoriaConstants::COLUMNAS_TIEMPO);
    }

    /** @param array<string, mixed> $adicionales datos derivados que no son columnas del modelo (p. ej. el desglose de un pago) */
    public function registrarCreacion(string $modulo, string $descripcion, Model $modelo, array $adicionales = [], ?AccionAuditoria $accion = null): void
    {
        $this->registrar(
            $accion ?? AccionAuditoria::Crear,
            $modulo,
            $descripcion,
            self::entidadDe($modelo),
            (int) $modelo->getKey(),
            nuevos: [...$this->instantanea($modelo), ...$adicionales],
        );
    }

    /** Guarda solo los campos que cambiaron respecto a `$anteriores` (de `instantanea()`); si nada cambió, no emite. */
    public function registrarModificacion(
        string $modulo,
        string $descripcion,
        Model $modelo,
        array $anteriores,
        ?string $motivo = null,
        ?AccionAuditoria $accion = null,
        array $adicionales = [],
    ): void {
        [$antes, $despues] = self::soloCambios($anteriores, [...$this->instantanea($modelo), ...$adicionales]);

        if ($despues === []) {
            return;
        }

        $this->registrar(
            $accion ?? AccionAuditoria::Modificar,
            $modulo,
            $descripcion,
            self::entidadDe($modelo),
            (int) $modelo->getKey(),
            $antes,
            $despues,
            $motivo,
        );
    }

    /** Para `updateOrCreate`: el modelo ya guardado sabe si fue creado o cuáles campos cambió y cuánto valían antes. */
    public function registrarGuardado(string $modulo, string $descripcion, Model $modelo): void
    {
        if ($modelo->wasRecentlyCreated) {
            $this->registrarCreacion($modulo, $descripcion, $modelo);

            return;
        }

        $cambiados = array_keys(Arr::except($modelo->getChanges(), AuditoriaConstants::COLUMNAS_TIEMPO));

        if ($cambiados === []) {
            return;
        }

        // getChanges/getPrevious traen los valores crudos de la base (p. ej. centavos): se pasan por los casts para que coincidan con los demás eventos.
        $previo = $modelo->newFromBuilder([...$modelo->getRawOriginal(), ...$modelo->getPrevious()]);

        $this->registrar(
            AccionAuditoria::Modificar,
            $modulo,
            $descripcion,
            self::entidadDe($modelo),
            (int) $modelo->getKey(),
            Arr::only($previo->attributesToArray(), $cambiados),
            Arr::only($modelo->attributesToArray(), $cambiados),
        );
    }

    public function registrarEliminacion(string $modulo, string $descripcion, Model $modelo, ?string $motivo = null, ?AccionAuditoria $accion = null): void
    {
        $this->registrar(
            $accion ?? AccionAuditoria::Eliminar,
            $modulo,
            $descripcion,
            self::entidadDe($modelo),
            (int) $modelo->getKey(),
            anteriores: $this->instantanea($modelo),
            motivo: $motivo,
        );
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

    private static function entidadDe(Model $modelo): string
    {
        return Str::snake(class_basename($modelo));
    }

    /** Compara tras normalizar a JSON (Dinero, enums y fechas se comparan como se van a guardar). */
    private static function soloCambios(array $anteriores, array $nuevos): array
    {
        try {
            $anteriores = json_decode(json_encode($anteriores, JSON_THROW_ON_ERROR), true);
            $nuevos = json_decode(json_encode($nuevos, JSON_THROW_ON_ERROR), true);
        } catch (JsonException) {
            return [$anteriores, $nuevos];
        }

        $cambiados = array_filter(
            array_keys($anteriores + $nuevos),
            fn ($campo) => self::comparable($anteriores[$campo] ?? null) !== self::comparable($nuevos[$campo] ?? null),
        );

        return [Arr::only($anteriores, $cambiados), Arr::only($nuevos, $cambiados)];
    }

    /** Un atributo sin cast puede llegar como `5` o `"5"` según el origen: no es un cambio. */
    private static function comparable(mixed $valor): ?string
    {
        return match (true) {
            $valor === null => null,
            is_bool($valor) => $valor ? '1' : '0',
            is_scalar($valor) => (string) $valor,
            default => (string) json_encode($valor),
        };
    }
}
