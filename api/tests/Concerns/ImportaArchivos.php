<?php

namespace Tests\Concerns;

use App\Importacion\Constants\ImportacionConstants;
use App\Importacion\Models\Importacion;
use App\Importacion\Models\ImportacionError;
use App\Importacion\Services\ImportacionEjecutorService;
use Database\Seeders\PermisoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * Recorre el flujo completo de una importación sin worker: la cola se finge y los jobs se ejecutan al pedido,
 * igual que los corre `php artisan queue:work` con sesión ausente (el ejecutor fija su propio autor).
 */
trait ImportaArchivos
{
    protected function prepararImportaciones(): void
    {
        $this->seed(PermisoSeeder::class);
        Queue::fake();
        Storage::fake(ImportacionConstants::DISCO);
    }

    protected function subirArchivo(string $tipo, string $csv, array $datos = [], string $nombre = 'datos.csv'): int
    {
        return $this->postJson('/api/v1/importaciones', [
            'tipo' => $tipo,
            'archivo' => UploadedFile::fake()->createWithContent($nombre, $csv),
            ...$datos,
        ])->assertStatus(202)->json('importacion.id');
    }

    protected function ejecutarValidacion(int $id): void
    {
        app(ImportacionEjecutorService::class)->validar($id, $this->importacion($id)->empresa_id);
    }

    protected function ejecutarEscritura(int $id): void
    {
        app(ImportacionEjecutorService::class)->escribir($id, $this->importacion($id)->empresa_id);
    }

    /** Sube, valida, registra las decisiones dadas, confirma y escribe. Devuelve el id. */
    protected function importarCompleto(string $tipo, string $csv, array $decisiones = [], string $nombre = 'datos.csv', array $datos = []): int
    {
        $id = $this->subirArchivo($tipo, $csv, $datos, $nombre);
        $this->ejecutarValidacion($id);

        if ($decisiones !== []) {
            $this->postJson("/api/v1/importaciones/{$id}/decisiones", $decisiones)->assertOk();
        }

        $this->postJson("/api/v1/importaciones/{$id}/confirmar")->assertStatus(202);
        $this->ejecutarEscritura($id);

        return $id;
    }

    protected function importacion(int $id): Importacion
    {
        return Importacion::query()->findOrFail($id);
    }

    /** @return list<array{fila: ?int, columna: ?string, severidad: string, fase: string, motivo: string}> */
    protected function erroresDe(int $id): array
    {
        return ImportacionError::query()
            ->where('importacion_id', $id)
            ->orderBy('id')
            ->get()
            ->map(fn (ImportacionError $error) => [
                'fila' => $error->fila,
                'columna' => $error->columna,
                'severidad' => $error->severidad->value,
                'fase' => $error->fase->value,
                'motivo' => $error->motivo,
            ])
            ->all();
    }

    /** @return list<string> motivos de los errores (no avisos) */
    protected function motivosDeRechazo(int $id): array
    {
        return array_values(array_map(
            fn (array $error) => $error['motivo'],
            array_filter($this->erroresDe($id), fn (array $error) => $error['severidad'] === 'error' && $error['fase'] === 'validacion'),
        ));
    }
}
