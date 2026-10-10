<?php

namespace Tests\Concerns;

use App\Conciliacion\Constants\ConciliacionConstants;
use App\Conciliacion\Models\ExtractoBancario;
use App\Conciliacion\Services\ExtractoBancarioService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/** Sube un extracto sin worker: la cola se finge y el job se ejecuta al pedido, igual que `ImportaArchivos`. */
trait CargaExtractosBancarios
{
    protected function prepararConciliacion(): void
    {
        Queue::fake();
        Storage::fake(ConciliacionConstants::DISCO);
    }

    protected function cargarExtracto(int $cuentaBancariaId, UploadedFile $archivo, string $mesPrimeraFila = '2026-07'): TestResponse
    {
        return $this->postJson("/api/v1/conciliacion/cuentas-bancarias/{$cuentaBancariaId}/extractos", [
            'archivo' => $archivo,
            'mes_primera_fila' => $mesPrimeraFila,
        ]);
    }

    protected function procesarExtracto(int $id, int $empresaId): void
    {
        app(ExtractoBancarioService::class)->procesar($id, $empresaId);
    }

    protected function extracto(int $id): ExtractoBancario
    {
        return ExtractoBancario::query()->findOrFail($id);
    }
}
