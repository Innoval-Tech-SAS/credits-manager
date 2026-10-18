<?php

namespace Tests\Concerns;

use App\Respaldos\Models\ConfiguracionRespaldoExterno;
use App\Respaldos\Repositories\ConfiguracionRespaldoExternoRepository;
use App\Respaldos\Services\FabricaDiscoRespaldoExterno;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/** Ningún test sube nada a un S3 real: la fábrica entrega un disco local falso, que solo registra. */
trait SimulaRespaldoExterno
{
    protected function simulaRespaldoExterno(): Filesystem
    {
        $disco = Storage::fake('respaldo_externo_prueba');

        $this->app->instance(FabricaDiscoRespaldoExterno::class, $this->fabricaQueEntrega($disco));

        return $disco;
    }

    protected function fabricaQueEntrega(Filesystem $disco): FabricaDiscoRespaldoExterno
    {
        return new class($disco) extends FabricaDiscoRespaldoExterno
        {
            public function __construct(private readonly Filesystem $disco) {}

            public function paraConfiguracion(ConfiguracionRespaldoExterno $configuracion): Filesystem
            {
                return $this->disco;
            }
        };
    }

    protected function activarRespaldoExterno(array $overrides = []): ConfiguracionRespaldoExterno
    {
        $configuracion = app(ConfiguracionRespaldoExternoRepository::class)->obtener();

        $configuracion->fill(array_merge([
            'activa' => true,
            'bucket' => 'bucket-de-prueba',
            'region' => 'us-east-1',
            'clave_acceso' => 'clave-de-prueba',
        ], $overrides));
        $configuracion->clave_secreta = $overrides['clave_secreta'] ?? 'secreto-de-prueba';
        $configuracion->save();

        return $configuracion;
    }
}
