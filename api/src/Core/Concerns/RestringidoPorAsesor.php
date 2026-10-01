<?php

namespace App\Core\Concerns;

use App\Core\Scopes\AlcanceAsesorScope;

/** Limita el modelo a los clientes del asesor sin alcance global (ver `AlcanceAsesorScope`). */
trait RestringidoPorAsesor
{
    public static function bootRestringidoPorAsesor(): void
    {
        static::addGlobalScope(new AlcanceAsesorScope(static::columnaDeClienteParaAlcance()));
    }

    /** Columna que apunta al cliente, o `null` si el propio modelo es el cliente. */
    abstract protected static function columnaDeClienteParaAlcance(): ?string;
}
