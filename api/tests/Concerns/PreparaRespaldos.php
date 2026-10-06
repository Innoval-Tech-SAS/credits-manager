<?php

namespace Tests\Concerns;

use App\Respaldos\Services\VolcadoBaseDatosService;
use Illuminate\Support\Facades\File;
use Tests\Support\VolcadoBaseDatosFalso;

/** Apunta los respaldos a carpetas temporales propias de cada test y cambia el volcado por uno de prueba. */
trait PreparaRespaldos
{
    protected string $raizRespaldos;

    /** @var list<string> */
    protected array $directoriosOrigen = [];

    protected function prepararRespaldos(): void
    {
        $base = sys_get_temp_dir().'/respaldos-test-'.uniqid();
        $this->raizRespaldos = $base.'/respaldos';
        $this->directoriosOrigen = [$base.'/app', $base.'/exportaciones'];

        foreach ($this->directoriosOrigen as $directorio) {
            File::ensureDirectoryExists($directorio.'/privado');
            file_put_contents($directorio.'/privado/archivo.txt', 'contenido de '.basename($directorio));
        }

        config([
            'respaldos.ruta' => $this->raizRespaldos,
            'respaldos.directorios' => $this->directoriosOrigen,
            'database.connections.mysql.password' => 'clave-secreta-de-prueba',
        ]);

        $this->app->instance(VolcadoBaseDatosService::class, new VolcadoBaseDatosFalso);
    }

    protected function limpiarRespaldos(): void
    {
        File::deleteDirectory(dirname($this->raizRespaldos));
    }
}
