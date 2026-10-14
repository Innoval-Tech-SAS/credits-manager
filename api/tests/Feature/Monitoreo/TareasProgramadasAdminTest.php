<?php

namespace Tests\Feature\Monitoreo;

use App\Auditoria\Enums\AccionAuditoria;
use App\Monitoreo\Constants\TareaProgramadaConstants;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\AfirmaAuditoria;
use Tests\TestCase;

class TareasProgramadasAdminTest extends TestCase
{
    use ActsAsAdmin;
    use AfirmaAuditoria;
    use RefreshDatabase;

    public function test_sin_autenticar_responde_401(): void
    {
        $this->getJson('/api/v1/tareas-programadas')->assertUnauthorized();
        $this->patchJson('/api/v1/tareas-programadas/metas:evaluar', ['hora' => '23:00', 'activa' => true])->assertUnauthorized();
        $this->getJson('/api/v1/tareas-programadas/metas:evaluar/historial')->assertUnauthorized();
    }

    public function test_el_administrador_de_una_empresa_no_ve_las_tareas_programadas(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/tareas-programadas')->assertForbidden();
        $this->patchJson('/api/v1/tareas-programadas/metas:evaluar', ['hora' => '23:00', 'activa' => true])->assertForbidden();
        $this->getJson('/api/v1/tareas-programadas/metas:evaluar/historial')->assertForbidden();
    }

    public function test_index_lista_el_catalogo_con_las_horas_sembradas(): void
    {
        $this->actingAsSuperAdmin();

        $respuesta = $this->getJson('/api/v1/tareas-programadas')->assertOk();

        $tareas = collect($respuesta->json('tareas'))->keyBy('clave');
        $this->assertSame(collect(TareaProgramadaConstants::CLAVES)->sort()->values()->all(), $tareas->keys()->sort()->values()->all());
        $this->assertSame('00:05', $tareas['cuotas:actualizar-vencidas']['hora']);
        $this->assertSame('01:00', $tareas['respaldos:ejecutar']['hora']);
        $this->assertFalse($tareas['respaldos:ejecutar']['pausable']);
        $this->assertTrue($tareas['cuotas:actualizar-vencidas']['pausable']);
        $this->assertNull($tareas['cuotas:actualizar-vencidas']['ultima_ejecucion']);
    }

    public function test_actualizar_cambia_hora_y_pausa_y_queda_en_la_bitacora(): void
    {
        $this->actingAsSuperAdmin();

        $this->patchJson('/api/v1/tareas-programadas/metas:evaluar', ['hora' => '22:30', 'activa' => false])->assertOk()
            ->assertJsonPath('tarea.clave', 'metas:evaluar')
            ->assertJsonPath('tarea.activa', false);

        $this->assertDatabaseHas('tareas_programadas', ['clave' => 'metas:evaluar', 'hora' => '22:30:00', 'activa' => false]);

        $eventos = $this->eventosDeAuditoria(AccionAuditoria::Modificar->value, 'tareas_programadas');
        $this->assertCount(1, $eventos);
        $this->assertNull($eventos[0]['empresa_id']);
    }

    public function test_no_se_puede_pausar_el_respaldo_automatico(): void
    {
        $this->actingAsSuperAdmin();

        $this->patchJson('/api/v1/tareas-programadas/respaldos:ejecutar', ['hora' => '02:00', 'activa' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('activa');

        $this->assertDatabaseHas('tareas_programadas', ['clave' => 'respaldos:ejecutar', 'hora' => '01:00:00', 'activa' => true]);
    }

    public function test_actualizar_rechaza_una_hora_con_formato_invalido(): void
    {
        $this->actingAsSuperAdmin();

        $this->patchJson('/api/v1/tareas-programadas/metas:evaluar', ['hora' => '25:99', 'activa' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('hora');
    }

    public function test_actualizar_una_clave_desconocida_responde_404(): void
    {
        $this->actingAsSuperAdmin();

        $this->patchJson('/api/v1/tareas-programadas/no-existe', ['hora' => '10:00', 'activa' => true])->assertNotFound();
    }

    public function test_historial_pagina_las_ejecuciones_de_una_clave_mas_recientes_primero(): void
    {
        $this->actingAsSuperAdmin();

        $this->insertarEjecucion('metas:evaluar', 'ok', now()->subDays(2));
        $this->insertarEjecucion('metas:evaluar', 'error', now()->subDay(), 'Fallo de prueba');
        $this->insertarEjecucion('cuotas:actualizar-vencidas', 'ok', now());

        $respuesta = $this->getJson('/api/v1/tareas-programadas/metas:evaluar/historial')->assertOk();

        $respuesta->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.resultado', 'error')
            ->assertJsonPath('data.0.error', 'Fallo de prueba')
            ->assertJsonPath('data.1.resultado', 'ok');
    }

    public function test_historial_de_una_clave_desconocida_responde_404(): void
    {
        $this->actingAsSuperAdmin();

        $this->getJson('/api/v1/tareas-programadas/no-existe/historial')->assertNotFound();
    }

    private function insertarEjecucion(string $clave, string $resultado, \DateTimeInterface $iniciadoEn, ?string $error = null): void
    {
        DB::table('ejecuciones_tareas_programadas')->insert([
            'tarea_clave' => $clave,
            'iniciado_en' => $iniciadoEn,
            'finalizado_en' => $iniciadoEn,
            'resultado' => $resultado,
            'error' => $error,
        ]);
    }
}
