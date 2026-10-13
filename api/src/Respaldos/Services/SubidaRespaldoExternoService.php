<?php

namespace App\Respaldos\Services;

use App\Respaldos\Constants\RespaldosConstants;
use App\Respaldos\Enums\ClaseRespaldo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Sube una copia del respaldo principal al disco `s3` (cualquier proveedor compatible con S3, vía
 * `AWS_ENDPOINT`). Opcional: sin credenciales configuradas no hace nada, solo avisa en el log — el
 * respaldo local ya quedó completo antes de llamar a este servicio, esto es un paso adicional.
 */
class SubidaRespaldoExternoService
{
    private const ARCHIVOS = [
        RespaldosConstants::ARCHIVO_BASE,
        RespaldosConstants::ARCHIVO_ARCHIVOS,
        RespaldosConstants::ARCHIVO_SUMAS,
        RespaldosConstants::ARCHIVO_DATOS,
    ];

    public function subir(string $rutaLocal, ClaseRespaldo $clase): void
    {
        if (! $this->configurado()) {
            Log::warning('Respaldo externo omitido: el disco "s3" no tiene credenciales configuradas.');

            return;
        }

        $prefijo = $clase->value.'/'.basename($rutaLocal);

        foreach (self::ARCHIVOS as $archivo) {
            try {
                Storage::disk('s3')->put("{$prefijo}/{$archivo}", file_get_contents("{$rutaLocal}/{$archivo}"));
            } catch (Throwable $e) {
                Log::warning("Respaldo externo: fallo subiendo {$archivo}: {$e->getMessage()}");
                $this->recordarResultado(basename($rutaLocal), false, "No se pudo subir {$archivo}.");

                return;
            }
        }

        $this->recordarResultado(basename($rutaLocal), true, null);
    }

    public function configurado(): bool
    {
        return filled(config('filesystems.disks.s3.key')) && filled(config('filesystems.disks.s3.bucket'));
    }

    /** @return array{respaldo: string, exitoso: bool, detalle: ?string, registrado_en: string}|null */
    public function ultimoResultado(): ?array
    {
        $resultado = Cache::get(RespaldosConstants::LLAVE_ULTIMA_SUBIDA_EXTERNA);

        return is_array($resultado) ? $resultado : null;
    }

    /** Para el panel de salud. El detalle es un mensaje fijo: nunca el error crudo del proveedor (puede llevar la URL o el bucket). */
    private function recordarResultado(string $respaldo, bool $exitoso, ?string $detalle): void
    {
        Cache::forever(RespaldosConstants::LLAVE_ULTIMA_SUBIDA_EXTERNA, [
            'respaldo' => $respaldo,
            'exitoso' => $exitoso,
            'detalle' => $detalle,
            'registrado_en' => now()->toIso8601String(),
        ]);
    }
}
