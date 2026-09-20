<?php

namespace App\Auth\Services;

use App\Usuarios\Models\Usuario;
use App\Usuarios\Repositories\UsuarioRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(protected UsuarioRepository $usuarios) {}

    public function iniciarSesion(Request $peticion, string $usuario, string $clave): void
    {
        if (Auth::check()) {
            Auth::guard('web')->logout();
        }

        $autenticado = Auth::attempt([
            'usuario' => strtolower($usuario),
            'password' => $clave,
            'activo' => true,
        ]);

        if (! $autenticado) {
            throw ValidationException::withMessages([
                'usuario' => __('validation.custom.usuario.credenciales_invalidas'),
            ]);
        }

        $peticion->session()->regenerate();
    }

    public function cerrarSesion(Request $peticion): void
    {
        Auth::guard('web')->logout();

        $peticion->session()->invalidate();
        $peticion->session()->regenerateToken();
    }

    public function cambiarClavePropia(Usuario $usuario, string $claveActual, string $claveNueva): void
    {
        if (! Hash::check($claveActual, $usuario->password_hash)) {
            throw ValidationException::withMessages([
                'clave_actual' => __('validation.custom.clave_actual.incorrecta'),
            ]);
        }

        $this->usuarios->actualizar($usuario, ['password_hash' => $claveNueva]);
    }
}
