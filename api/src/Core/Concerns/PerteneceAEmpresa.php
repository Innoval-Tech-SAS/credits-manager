<?php

namespace App\Core\Concerns;

use App\Core\Scopes\EmpresaScope;
use Illuminate\Support\Facades\Auth;

/**
 * Aísla el modelo por empresa: aplica el filtro de consulta (EmpresaScope) y
 * rellena `empresa_id` automáticamente al crear, desde el usuario autenticado.
 */
trait PerteneceAEmpresa
{
    public static function bootPerteneceAEmpresa(): void
    {
        static::addGlobalScope(new EmpresaScope);

        static::creating(function ($modelo) {
            if (! $modelo->empresa_id && Auth::check()) {
                $modelo->empresa_id = Auth::user()->empresa_id;
            }
        });
    }
}
