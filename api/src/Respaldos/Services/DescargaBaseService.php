<?php

namespace App\Respaldos\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

/**
 * Arma el archivo que el usuario descarga desde la interfaz: `.sql.gz`, con la estructura (solo en MySQL,
 * con `mariadb-dump`) y los datos. Se escribe en una carpeta privada y el controlador lo borra al enviarlo.
 */
class DescargaBaseService
{
    public function __construct(
        private VolcadoBaseDatosService $volcado,
        private VolcadoDatosService $datos,
    ) {}

    /** Toda la instancia, como la copia de seguridad, sin hashes ni tokens. */
    public function baseCompleta(): string
    {
        return $this->generar(null, conBorrado: true);
    }

    /** Solo las filas de la empresa, en todas las tablas. Sin `DROP`: restaurarla no borra lo existente. */
    public function empresa(int $empresaId): string
    {
        return $this->generar($empresaId, conBorrado: false);
    }

    private function generar(?int $empresaId, bool $conBorrado): string
    {
        $directorio = storage_path('app/private/descargas');
        File::ensureDirectoryExists($directorio);

        $base = $directorio.'/'.Str::uuid()->toString();
        $ruta = $base.'.sql.gz';
        $esquema = $base.'.esquema.sql';

        try {
            $gz = gzopen($ruta, 'wb9');

            if ($gz === false) {
                throw new \RuntimeException('No se pudo crear el archivo de descarga.');
            }

            gzwrite($gz, '-- credits-manager: descarga generada el '.now()->toIso8601String()."\n");
            gzwrite($gz, "-- Contraseñas y tokens de sesión omitidos.\n");
            gzwrite($gz, "SET FOREIGN_KEY_CHECKS=0;\n");

            if (DB::getDriverName() === 'mysql') {
                $this->volcado->volcarEsquema($esquema, $conBorrado);
                $this->copiarSinDefinidor($esquema, $gz);
            }

            $omitidas = $this->datos->escribir(fn (string $sql) => gzwrite($gz, $sql), $empresaId);

            if ($omitidas !== []) {
                gzwrite($gz, "\n-- Tablas no incluidas (no pertenecen a la empresa): ".implode(', ', $omitidas)."\n");
            }

            gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
            gzclose($gz);
        } catch (Throwable $e) {
            @unlink($ruta);

            throw $e;
        } finally {
            @unlink($esquema);
        }

        return $ruta;
    }

    /** El definidor de triggers apunta a una cuenta de este servidor; sin él el archivo es portable. */
    private function copiarSinDefinidor(string $origen, $destino): void
    {
        $entrada = fopen($origen, 'rb');

        while (($linea = fgets($entrada)) !== false) {
            gzwrite($destino, preg_replace('/DEFINER=`[^`]*`@`[^`]*`/', '', $linea));
        }

        fclose($entrada);
    }
}
