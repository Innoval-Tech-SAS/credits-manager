<?php

namespace App\Respaldos\Services;

use App\Respaldos\Constants\RespaldosConstants;
use App\Respaldos\Enums\ClaseRespaldo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

/**
 * Crea un respaldo completo (base + archivos + sumas + ficha) y lo copia a su clase. El respaldo
 * se escribe en una carpeta temporal y solo se renombra cuando está completo: nunca queda a medias
 * a la vista de quien copia. La retención corre solo tras un respaldo correcto.
 */
class RespaldoService
{
    public function __construct(
        protected VolcadoBaseDatosService $volcado,
        protected ArchivosRespaldoService $archivos,
        protected RetencionRespaldosService $retencion,
    ) {}

    /**
     * Automático: diario, y copia semanal los domingos y mensual el día 1. Manual: solo la clase manual.
     *
     * @return array<string, string> ruta de cada copia creada, por clase
     */
    public function ejecutar(CarbonImmutable $ahora, bool $manual): array
    {
        $clases = $manual ? [ClaseRespaldo::Manual] : $this->clasesAutomaticas($ahora);
        $carpeta = $ahora->format(RespaldosConstants::FORMATO_CARPETA);
        $principal = array_shift($clases);

        $creadas = [$principal->value => $this->crear($principal, $carpeta, $ahora)];

        foreach ($clases as $clase) {
            $destino = $this->retencion->carpetaDeClase($clase).'/'.$carpeta;
            File::copyDirectory($creadas[$principal->value], $destino);
            $creadas[$clase->value] = $destino;
        }

        $this->retencion->aplicar($ahora);

        return $creadas;
    }

    /** @return list<ClaseRespaldo> */
    private function clasesAutomaticas(CarbonImmutable $ahora): array
    {
        $clases = [ClaseRespaldo::Diario];

        if ($ahora->isSunday()) {
            $clases[] = ClaseRespaldo::Semanal;
        }

        if ($ahora->day === 1) {
            $clases[] = ClaseRespaldo::Mensual;
        }

        return $clases;
    }

    private function crear(ClaseRespaldo $clase, string $carpeta, CarbonImmutable $ahora): string
    {
        $carpetaClase = $this->retencion->carpetaDeClase($clase);
        $temporal = $carpetaClase.'/'.RespaldosConstants::PREFIJO_TEMPORAL.$carpeta;
        $definitiva = $carpetaClase.'/'.$carpeta;

        if (is_dir($definitiva)) {
            throw new RuntimeException('Ya existe un respaldo con esa fecha y hora.');
        }

        File::ensureDirectoryExists($temporal);

        try {
            $this->volcado->volcar($temporal.'/'.RespaldosConstants::ARCHIVO_BASE);
            $this->archivos->archivar($temporal.'/'.RespaldosConstants::ARCHIVO_ARCHIVOS);
            $this->escribirSumas($temporal);
            $this->escribirFicha($temporal, $clase, $ahora);

            rename($temporal, $definitiva);
        } catch (Throwable $e) {
            File::deleteDirectory($temporal);

            throw $e;
        }

        return $definitiva;
    }

    /** Formato de `sha256sum`: se verifica con `sha256sum -c SHA256SUMS` en cualquier equipo. */
    private function escribirSumas(string $carpeta): void
    {
        $lineas = [];

        foreach ([RespaldosConstants::ARCHIVO_BASE, RespaldosConstants::ARCHIVO_ARCHIVOS] as $archivo) {
            $lineas[] = hash_file('sha256', $carpeta.'/'.$archivo).'  '.$archivo;
        }

        file_put_contents($carpeta.'/'.RespaldosConstants::ARCHIVO_SUMAS, implode("\n", $lineas)."\n");
    }

    /** Sin usuario, contraseña ni host de la base: la ficha es pública para quien copia el respaldo. */
    private function escribirFicha(string $carpeta, ClaseRespaldo $clase, CarbonImmutable $ahora): void
    {
        $ficha = [
            'aplicacion' => config('app.name'),
            'clase' => $clase->value,
            'creado_en' => $ahora->toIso8601String(),
            'zona_horaria' => config('app.timezone'),
            'base_datos' => [
                'nombre' => config('database.connections.mysql.database'),
                'formato' => 'SQL (mariadb-dump), comprimido con gzip',
                'tablas_excluidas' => config('respaldos.tablas_excluidas'),
            ],
            'archivos' => [
                'formato' => 'tar comprimido con gzip',
                'contenido' => array_map('basename', config('respaldos.directorios')),
            ],
            'verificacion' => RespaldosConstants::ARCHIVO_SUMAS.' (SHA-256)',
            'retencion_dias' => $clase->diasRetencion(),
        ];

        file_put_contents(
            $carpeta.'/'.RespaldosConstants::ARCHIVO_DATOS,
            json_encode($ficha, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n",
        );
    }
}
