<?php

namespace App\Auditoria\Data;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Exceptions\EventoAuditoriaInvalidoException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use JsonException;

/**
 * Evento de auditoría inmutable. Todo se valida y normaliza al construirlo (longitudes de
 * columna, UTF-8, JSON serializable, sin campos sensibles), de modo que al stream de Redis
 * solo llegan eventos que la tabla puede insertar sin error.
 */
final readonly class EventoAuditoria
{
    private const FORMATO_FECHA = 'Y-m-d\TH:i:s.vP';

    private function __construct(
        public string $eventoId,
        public AccionAuditoria $accion,
        public string $modulo,
        public string $descripcion,
        public AutorAuditoria $autor,
        public CarbonImmutable $fechaHora,
        public ?int $empresaId,
        public ?string $ip,
        public ?string $entidad,
        public ?int $entidadId,
        public ?array $valoresAnteriores,
        public ?array $valoresNuevos,
        public ?string $motivo,
    ) {}

    public static function crear(
        AccionAuditoria $accion,
        string $modulo,
        string $descripcion,
        AutorAuditoria $autor,
        ?int $empresaId,
        ?string $ip = null,
        ?string $entidad = null,
        ?int $entidadId = null,
        ?array $valoresAnteriores = null,
        ?array $valoresNuevos = null,
        ?string $motivo = null,
        ?string $eventoId = null,
        ?CarbonImmutable $fechaHora = null,
    ): self {
        $eventoId ??= (string) Str::uuid();

        if (! Str::isUuid($eventoId)) {
            throw new EventoAuditoriaInvalidoException('evento_id no es un UUID válido.');
        }

        $modulo = self::limitar($modulo, AuditoriaConstants::LONGITUD_MODULO);
        $descripcion = self::limitar($descripcion, AuditoriaConstants::LONGITUD_DESCRIPCION);

        if ($modulo === '' || $descripcion === '') {
            throw new EventoAuditoriaInvalidoException('modulo y descripcion son obligatorios.');
        }

        return new self(
            eventoId: strtolower($eventoId),
            accion: $accion,
            modulo: $modulo,
            descripcion: $descripcion,
            autor: new AutorAuditoria(
                usuarioId: $autor->usuarioId,
                nombre: self::limitarOpcional($autor->nombre, AuditoriaConstants::LONGITUD_TEXTO_AUTOR),
                usuario: self::limitarOpcional($autor->usuario, AuditoriaConstants::LONGITUD_TEXTO_AUTOR),
                rol: self::limitarOpcional($autor->rol, AuditoriaConstants::LONGITUD_TEXTO_AUTOR),
                esSuperadmin: $autor->esSuperadmin,
            ),
            fechaHora: self::aMilisegundos($fechaHora ?? CarbonImmutable::now()),
            empresaId: $empresaId,
            ip: self::limitarOpcional($ip, AuditoriaConstants::LONGITUD_IP),
            entidad: self::limitarOpcional($entidad, AuditoriaConstants::LONGITUD_ENTIDAD),
            entidadId: $entidadId,
            valoresAnteriores: self::normalizarValores($valoresAnteriores),
            valoresNuevos: self::normalizarValores($valoresNuevos),
            motivo: self::limitarOpcional($motivo, AuditoriaConstants::LONGITUD_MOTIVO),
        );
    }

    public static function desdeArray(array $datos): self
    {
        $accion = AccionAuditoria::tryFrom((string) ($datos['accion'] ?? ''));

        if ($accion === null || empty($datos['fecha_hora'])) {
            throw new EventoAuditoriaInvalidoException('El evento no tiene accion o fecha_hora válidas.');
        }

        try {
            $fechaHora = CarbonImmutable::parse($datos['fecha_hora'])->setTimezone(config('app.timezone'));
        } catch (\Throwable $excepcion) {
            throw new EventoAuditoriaInvalidoException('fecha_hora inválida.', previous: $excepcion);
        }

        return self::crear(
            accion: $accion,
            modulo: (string) ($datos['modulo'] ?? ''),
            descripcion: (string) ($datos['descripcion'] ?? ''),
            autor: AutorAuditoria::desdeArray((array) ($datos['autor'] ?? [])),
            empresaId: isset($datos['empresa_id']) ? (int) $datos['empresa_id'] : null,
            ip: $datos['ip'] ?? null,
            entidad: $datos['entidad'] ?? null,
            entidadId: isset($datos['entidad_id']) ? (int) $datos['entidad_id'] : null,
            valoresAnteriores: $datos['valores_anteriores'] ?? null,
            valoresNuevos: $datos['valores_nuevos'] ?? null,
            motivo: $datos['motivo'] ?? null,
            eventoId: (string) ($datos['evento_id'] ?? ''),
            fechaHora: $fechaHora,
        );
    }

    public function aArray(): array
    {
        return [
            'evento_id' => $this->eventoId,
            'accion' => $this->accion->value,
            'modulo' => $this->modulo,
            'descripcion' => $this->descripcion,
            'autor' => $this->autor->aArray(),
            'fecha_hora' => $this->fechaHora->format(self::FORMATO_FECHA),
            'empresa_id' => $this->empresaId,
            'ip' => $this->ip,
            'entidad' => $this->entidad,
            'entidad_id' => $this->entidadId,
            'valores_anteriores' => $this->valoresAnteriores,
            'valores_nuevos' => $this->valoresNuevos,
            'motivo' => $this->motivo,
        ];
    }

    public function aJson(): string
    {
        return json_encode($this->aArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }

    /** La columna y el payload guardan milisegundos: se normaliza aquí para que la ida y vuelta sea exacta. */
    private static function aMilisegundos(CarbonImmutable $fecha): CarbonImmutable
    {
        return $fecha->setMicrosecond(intdiv($fecha->microsecond, 1000) * 1000);
    }

    private static function limitarOpcional(?string $texto, int $maximo): ?string
    {
        if ($texto === null) {
            return null;
        }

        $texto = self::limitar($texto, $maximo);

        return $texto === '' ? null : $texto;
    }

    private static function limitar(string $texto, int $maximo): string
    {
        $texto = trim(mb_convert_encoding($texto, 'UTF-8', 'UTF-8'));

        return mb_strlen($texto) > $maximo ? mb_substr($texto, 0, $maximo - 1).'…' : $texto;
    }

    private static function normalizarValores(?array $valores): ?array
    {
        if ($valores === null || $valores === []) {
            return null;
        }

        try {
            $json = json_encode(
                self::quitarSensibles($valores),
                JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION,
            );
        } catch (JsonException $excepcion) {
            throw new EventoAuditoriaInvalidoException('Los valores del evento no son serializables a JSON.', previous: $excepcion);
        }

        if (strlen($json) > AuditoriaConstants::BYTES_MAXIMOS_VALORES) {
            return ['_truncado' => true, '_bytes' => strlen($json)];
        }

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    private static function quitarSensibles(array $valores): array
    {
        foreach ($valores as $clave => $valor) {
            if (is_string($clave) && in_array(strtolower($clave), AuditoriaConstants::CAMPOS_SENSIBLES, true)) {
                unset($valores[$clave]);

                continue;
            }

            if (is_array($valor)) {
                $valores[$clave] = self::quitarSensibles($valor);
            }
        }

        return $valores;
    }
}
