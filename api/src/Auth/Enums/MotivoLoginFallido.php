<?php

namespace App\Auth\Enums;

enum MotivoLoginFallido: string
{
    case UsuarioInexistente = 'usuario_inexistente';
    case UsuarioDadoDeBaja = 'usuario_dado_de_baja';
    case ClaveIncorrecta = 'clave_incorrecta';
    case UsuarioInactivo = 'usuario_inactivo';

    public function descripcion(): string
    {
        return match ($this) {
            self::UsuarioInexistente => 'Usuario inexistente',
            self::UsuarioDadoDeBaja => 'Usuario dado de baja',
            self::ClaveIncorrecta => 'Contraseña incorrecta',
            self::UsuarioInactivo => 'Usuario inactivo',
        };
    }
}
