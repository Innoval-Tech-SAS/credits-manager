<?php

namespace App\Auditoria\Data;

use App\Usuarios\Models\Usuario;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Qué filas de la bitácora puede ver quien consulta o exporta. Se serializa dentro del job de
 * exportación: el worker no tiene sesión y nunca vuelve a derivarla de Auth.
 */
final readonly class Visibilidad
{
    private function __construct(
        public bool $todasLasEmpresas,
        public ?int $empresaId,
    ) {}

    public static function desdeUsuario(Usuario $usuario): self
    {
        if ($usuario->es_superadmin) {
            return new self(true, null);
        }

        if ($usuario->empresa_id === null) {
            throw new AuthorizationException('El usuario no pertenece a ninguna empresa.');
        }

        return new self(false, $usuario->empresa_id);
    }

    public static function deEmpresa(int $empresaId): self
    {
        return new self(false, $empresaId);
    }

    public static function deTodas(): self
    {
        return new self(true, null);
    }
}
