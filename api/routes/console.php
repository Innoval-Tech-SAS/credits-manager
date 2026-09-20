<?php

use App\Creditos\Services\CuotaService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Mismo trabajo que ya se dispara en cada GET de Créditos/Cartera/Calendario/Dashboard
 * (ver CuotaService::actualizarVencidas). Se agrega aquí para eventualmente dejar de
 * depender de la lectura y correrlo una sola vez al día — requiere que algo invoque
 * `php artisan schedule:run` (cron/supervisor), que hoy no existe en docker-compose.yaml.
 * Hasta que se agregue ese servicio, las llamadas en las rutas de lectura siguen siendo
 * la única garantía real de que las cuotas vencidas se marquen.
 */
Schedule::call(fn () => app(CuotaService::class)->actualizarVencidas())
    ->dailyAt('00:05')
    ->name('cuotas:actualizar-vencidas')
    ->withoutOverlapping();
