<?php

namespace App\Providers;

use App\Auditoria\Commands\EscucharAuditoriaCommand;
use App\Auditoria\Commands\EstadoAuditoriaCommand;
use App\Exportacion\Commands\LimpiarExportacionesCommand;
use App\Importacion\Commands\LimpiarImportacionesCommand;
use App\Metas\Commands\EvaluarMetasCommand;
use App\Redis\Contracts\RedisClienteInterface;
use App\Redis\Services\RedisCliente;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(RedisClienteInterface::class, RedisCliente::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->commands([EscucharAuditoriaCommand::class, EstadoAuditoriaCommand::class, LimpiarExportacionesCommand::class, LimpiarImportacionesCommand::class, EvaluarMetasCommand::class]);

        RateLimiter::for('login', function (Request $peticion) {
            $usuario = Str::lower((string) $peticion->input('usuario'));

            // El tope por IP acota las filas que un cliente puede provocar en la bitácora (solo inserción) probando usuarios distintos.
            return [
                Limit::perMinute(5)->by($usuario !== '' ? $usuario : $peticion->ip()),
                Limit::perMinute(30)->by('ip:'.$peticion->ip()),
            ];
        });
    }
}
