<?php

namespace App\Core\Consecutivos\Services;

use App\Core\Consecutivos\Enums\TipoDocumentoConsecutivo;
use App\Core\Consecutivos\Models\ConsecutivoDocumento;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Consecutivo por empresa y tipo de documento. Debe llamarse dentro de la transacción del documento: si esa transacción falla, el número se libera y no quedan huecos. */
class ConsecutivoDocumentoService
{
    /** @return array{numero: int, prefijo: string|null} */
    public function siguiente(TipoDocumentoConsecutivo $tipo): array
    {
        return DB::transaction(function () use ($tipo) {
            // insertOrIgnore evita el choque de dos primeras solicitudes simultáneas; la fila se bloquea antes de incrementar.
            ConsecutivoDocumento::insertOrIgnore([
                'empresa_id' => Auth::user()->empresa_id,
                'tipo_documento' => $tipo->value,
                'prefijo' => $tipo->prefijoInicial(),
                'ultimo_numero' => 0,
                'creado_en' => now(),
                'ultima_actualizacion' => now(),
            ]);

            $consecutivo = ConsecutivoDocumento::query()->where('tipo_documento', $tipo->value)->lockForUpdate()->firstOrFail();
            $numero = $consecutivo->ultimo_numero + 1;
            $consecutivo->update(['ultimo_numero' => $numero]);

            return ['numero' => $numero, 'prefijo' => $consecutivo->prefijo];
        });
    }
}
