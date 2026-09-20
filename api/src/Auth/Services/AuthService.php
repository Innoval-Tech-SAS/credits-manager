<?php

namespace App\Auth\Services;

use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Usuarios\Models\Usuario;
use App\Usuarios\Repositories\UsuarioRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        protected UsuarioRepository $usuarios,
        protected EmisorAuditoria $auditoria,
    ) {}

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

        $this->auditoria->registrar(AccionAuditoria::Login, 'auth', 'Inicio de sesión de '.Auth::user()->nombre, 'usuario', Auth::id());
    }

    public function cerrarSesion(Request $peticion): void
    {
        $this->auditoria->registrar(AccionAuditoria::Logout, 'auth', 'Cierre de sesión de '.$peticion->user()->nombre, 'usuario', $peticion->user()->id);

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
