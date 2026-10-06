<?php

namespace Tests\Feature\Respaldos;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ProgramacionRespaldoTest extends TestCase
{
    public function test_el_respaldo_diario_se_programa_a_la_una_sin_solaparse(): void
    {
        $tarea = collect(app(Schedule::class)->events())->first(fn (Event $evento) => $evento->description === 'respaldos:ejecutar');

        $this->assertNotNull($tarea, 'El respaldo no está programado.');
        $this->assertSame('0 1 * * *', $tarea->expression);
        $this->assertTrue($tarea->withoutOverlapping);
    }
}
