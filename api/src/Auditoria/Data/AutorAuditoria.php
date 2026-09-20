<?php

namespace App\Auditoria\Data;

use App\Usuarios\Models\Usuario;

/** Snapshot del autor tomado al emitir: el listener corre en otro proceso, sin sesión ni Auth. */
final readonly class AutorAuditoria
{
    public function __construct(
        public ?int $usuarioId,
        public ?string $nombre,
        public ?string $usuario,
        public ?string $rol,
        public bool $esSuperadmin,
    ) {}

    public static function desdeUsuario(?Usuario $usuario): self
    {
        if ($usuario === null) {
            return new self(null, null, null, null, false);
        }

        return new self(
            usuarioId: $usuario->id,
            nombre: $usuario->nombre,
            usuario: $usuario->usuario,
            rol: $usuario->loadMissing('rol')->rol?->nombre,
            esSuperadmin: (bool) $usuario->es_superadmin,
        );
    }

    public static function desdeArray(array $datos): self
    {
        return new self(
            usuarioId: isset($datos['usuario_id']) ? (int) $datos['usuario_id'] : null,
            nombre: $datos['nombre'] ?? null,
            usuario: $datos['usuario'] ?? null,
            rol: $datos['rol'] ?? null,
            esSuperadmin: (bool) ($datos['es_superadmin'] ?? false),
        );
    }

    public function aArray(): array
    {
        return [
            'usuario_id' => $this->usuarioId,
            'nombre' => $this->nombre,
            'usuario' => $this->usuario,
            'rol' => $this->rol,
            'es_superadmin' => $this->esSuperadmin,
        ];
    }
}
