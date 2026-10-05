<?php

namespace App\Usuarios\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Usuarios\Constants\UsuarioConstants;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['empresa_id', 'nombre', 'usuario', 'password_hash', 'rol_id', 'activo', 'es_global', 'es_superadmin'])]
#[Hidden(['password_hash'])]
class Usuario extends BaseModel implements AuthenticatableContract
{
    use Authenticatable;
    use PerteneceAEmpresa;

    protected $table = 'usuarios';

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    protected function usuario(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => strtolower($value),
        );
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class);
    }

    /** Excepciones puntuales sobre el permiso que da el rol: conceden o revocan un permiso específico. */
    public function permisosExtra(): BelongsToMany
    {
        return $this->belongsToMany(Permiso::class, 'usuario_permiso')
            ->using(UsuarioPermiso::class)
            ->withPivot(['concedido']);
    }

    /** Solo el rol asesor sin `es_global` queda limitado a sus clientes asignados; los demás roles ven toda su empresa. */
    public function restringidoAClientesPropios(): bool
    {
        return $this->rol?->nombre === UsuarioConstants::ROL_ASESOR && ! $this->es_global;
    }

    /** Id del asesor cuyo alcance limita los datos, o null si el usuario ve toda su empresa. */
    public function idAsesorConAlcance(): ?int
    {
        return $this->restringidoAClientesPropios() ? $this->id : null;
    }

    public function tienePermiso(string $modulo, string $accion): bool
    {
        if ($this->rol?->nombre === UsuarioConstants::ROL_ADMIN) {
            return true;
        }

        $excepcion = $this->permisosExtra()
            ->where('modulo', $modulo)
            ->where('accion', $accion)
            ->first();

        if ($excepcion) {
            return (bool) $excepcion->pivot->concedido;
        }

        return (bool) $this->rol?->permisos()
            ->where('modulo', $modulo)
            ->where('accion', $accion)
            ->exists();
    }

    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'activo' => 'boolean',
            'es_global' => 'boolean',
            'es_superadmin' => 'boolean',
        ];
    }
}
