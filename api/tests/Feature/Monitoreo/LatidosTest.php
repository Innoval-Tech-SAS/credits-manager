<?php

namespace Tests\Feature\Monitoreo;

use App\Monitoreo\Services\RegistroLatidos;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Mockery;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

class LatidosTest extends TestCase
{
    private function job(string $cola): Job
    {
        $job = Mockery::mock(Job::class);
        $job->shouldReceive('getQueue')->andReturn($cola);
        $job->shouldReceive('resolveName')->andReturn('App\\Exportacion\\Jobs\\ProcesarExportacionJob');
        // Laravel también escucha JobProcessing (ContextServiceProvider) y lee el payload del job.
        $job->shouldReceive('payload')->andReturn([]);

        return $job;
    }

    public function test_un_worker_en_espera_late_como_activo(): void
    {
        event(new Looping('redis', 'correos'));

        $this->assertSame('activo', app(RegistroLatidos::class)->leer('correos-worker')['estado']);
    }

    public function test_un_worker_ocupado_late_con_el_trabajo_y_vuelve_a_activo_al_terminar(): void
    {
        event(new Looping('redis', 'exportaciones'));
        event(new JobProcessing('redis', $this->job('exportaciones')));

        $latido = app(RegistroLatidos::class)->leer('exportaciones-worker');
        $this->assertSame('ocupado', $latido['estado']);
        $this->assertSame('App\\Exportacion\\Jobs\\ProcesarExportacionJob', $latido['detalle']);

        event(new JobProcessed('redis', $this->job('exportaciones')));

        $this->assertSame('activo', app(RegistroLatidos::class)->leer('exportaciones-worker')['estado']);
    }

    public function test_una_cola_que_no_es_del_compose_no_deja_latido(): void
    {
        event(new Looping('redis', 'default'));

        $this->assertNull(app(RegistroLatidos::class)->leer('default-worker'));
    }

    public function test_cada_schedule_run_es_un_latido_del_scheduler(): void
    {
        event(new CommandStarting('inspire', new ArrayInput([]), new NullOutput));
        $this->assertNull(app(RegistroLatidos::class)->leer('scheduler'));

        event(new CommandStarting('schedule:run', new ArrayInput([]), new NullOutput));
        $this->assertSame('activo', app(RegistroLatidos::class)->leer('scheduler')['estado']);
    }
}
