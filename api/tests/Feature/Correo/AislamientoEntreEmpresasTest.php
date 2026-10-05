<?php

namespace Tests\Feature\Correo;

use App\Correo\Enums\EstadoEnvioCorreo;
use App\Correo\Jobs\EnviarRecordatorioCobranzaJob;
use App\Correo\Mail\CorreoCobranza;
use App\Correo\Models\EnvioCorreo;
use App\Correo\Services\EnvioRecordatorioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\Concerns\SimulaCorreo;
use Tests\TestCase;

/** Prueba que la configuración de correo, los recordatorios y el job respetan la empresa (ver tenant-isolation). */
class AislamientoEntreEmpresasTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use RefreshDatabase;
    use SimulaCorreo;

    public function test_la_configuracion_de_una_empresa_no_se_ve_en_otra(): void
    {
        $this->actingAsAdmin();
        $this->configuracionDeCorreo(['remitente' => 'cobranza@empresa-a.com']);

        $this->actingAsAdmin();

        $this->getJson('/api/v1/correo/configuracion')
            ->assertOk()
            ->assertJsonPath('configuracion.estado', 'sin_configurar')
            ->assertDontSee('empresa-a.com');
    }

    public function test_no_se_puede_enviar_un_recordatorio_a_un_cliente_de_otra_empresa(): void
    {
        Queue::fake();
        $this->actingAsAdmin();
        $this->configuracionDeCorreo();
        $clienteAjeno = $this->crearCliente();
        $cuotaAjena = $this->crearCuota($this->crearCredito($clienteAjeno));

        $this->actingAsAdmin();
        $this->configuracionDeCorreo();

        $this->postJson("/api/v1/clientes/{$clienteAjeno->id}/recordatorios-correo", ['cuota_id' => $cuotaAjena->id])->assertNotFound();
        $this->getJson("/api/v1/clientes/{$clienteAjeno->id}/recordatorios-correo")->assertNotFound();
        $this->assertDatabaseCount('envios_correo', 0);
    }

    public function test_el_job_con_la_empresa_equivocada_no_envia_el_correo_ajeno(): void
    {
        Queue::fake();
        $fake = $this->simulaCorreo();
        $adminA = $this->actingAsAdmin();
        $this->configuracionDeCorreo();
        $cliente = $this->crearCliente(['correo' => 'juan@example.com']);
        $cuota = $this->crearCuota($this->crearCredito($cliente));
        $this->postJson("/api/v1/clientes/{$cliente->id}/recordatorios-correo", ['cuota_id' => $cuota->id])->assertStatus(202);

        $envio = EnvioCorreo::query()->firstOrFail();
        $empresaB = $this->crearEmpresaDePrueba();

        (new EnviarRecordatorioCobranzaJob($envio->id, $empresaB->id))->handle(app(EnvioRecordatorioService::class));

        $fake->assertNothingSent();
        $this->assertDatabaseHas('envios_correo', ['id' => $envio->id, 'estado' => EstadoEnvioCorreo::EnCola->value]);

        (new EnviarRecordatorioCobranzaJob($envio->id, (int) $adminA->empresa_id))->handle(app(EnvioRecordatorioService::class));

        $fake->assertSent(CorreoCobranza::class, 1);
    }
}
