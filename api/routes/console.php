<?php

use App\Creditos\Services\CuotaService;
use App\Respaldos\Constants\RespaldosConstants;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Marca la mora de todas las empresas una vez al día, sin sesión. Las llamadas a
 * `CuotaService::actualizarVencidas` en las lecturas de Créditos/Cartera/Calendario/Dashboard
 * se mantienen como respaldo por si el servicio `scheduler` estuviera detenido.
 */
Schedule::call(fn () => app(CuotaService::class)->actualizarVencidas())
    ->dailyAt('00:05')
    ->name('cuotas:actualizar-vencidas')
    ->withoutOverlapping(10);

Schedule::command('exportaciones:limpiar')
    ->dailyAt('03:00')
    ->name('exportaciones:limpiar')
    ->withoutOverlapping(30);

/** Cierra las metas vencidas y genera alertas, empresa por empresa (cada una con su `empresa_id` explícito). */
Schedule::command('metas:evaluar')
    ->dailyAt('23:55')
    ->name('metas:evaluar')
    ->withoutOverlapping(30);

/** Genera las alertas del calendario tributario por vencer, empresa por empresa (cada una con su `empresa_id` explícito). */
Schedule::command('obligaciones_tributarias:evaluar-alertas')
    ->dailyAt('06:00')
    ->name('obligaciones_tributarias:evaluar-alertas')
    ->withoutOverlapping(30);

Schedule::command('importaciones:limpiar')
    ->dailyAt('03:15')
    ->name('importaciones:limpiar')
    ->withoutOverlapping(30);

/**
 * Respaldo diario de base y archivos (los domingos y el día 1 también se copia a semanal y mensual).
 * Un fallo queda en el log del scheduler; el respaldo anterior sigue disponible.
 */
Schedule::command('respaldos:ejecutar')
    ->dailyAt(RespaldosConstants::HORA_AUTOMATICA)
    ->name('respaldos:ejecutar')
    ->withoutOverlapping(120)
    ->onFailure(fn () => Log::error('El respaldo automático falló; revisar la salida del servicio scheduler.'));
