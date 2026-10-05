<?php

use App\Creditos\Services\CuotaService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
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

Schedule::command('importaciones:limpiar')
    ->dailyAt('03:15')
    ->name('importaciones:limpiar')
    ->withoutOverlapping(30);
