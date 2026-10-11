<?php

namespace Tests\Feature\Correo;

use App\Correo\Enums\EstadoEnvioCorreo;
use App\Correo\Jobs\EnviarRecordatorioCobranzaJob;
use App\Correo\Models\EnvioCorreo;
use App\Creditos\Enums\EstadoCuota;
use App\Empresas\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\Concerns\SimulaCorreo;
use Tests\TestCase;

class ReintentarRecordatorioTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use RefreshDatabase;
    use SimulaCorreo;

    private function envioFallido(): array
    {
        Queue::fake();
        $this->configuracionDeCorreo();
        $this->plantillaDeCorreo();
        $cliente = $this->crearCliente(['correo' => 'juan@example.com']);
        $cuota = $this->crearCuota($this->crearCredito($cliente));
        $this->postJson("/api/v1/clientes/{$cliente->id}/recordatorios-correo", ['cuota_id' => $cuota->id])->assertStatus(202);

        $envio = EnvioCorreo::query()->firstOrFail();
        $envio->estado = EstadoEnvioCorreo::Fallido;
        $envio->detalle_error = 'fallo de prueba';
        $envio->save();

        return ['cliente' => $cliente, 'cuota' => $cuota, 'envio' => $envio];
    }

    public function test_reintentar_un_envio_fallido_lo_vuelve_a_encolar(): void
    {
        $this->actingAsAdmin();
        ['cliente' => $cliente, 'envio' => $envio] = $this->envioFallido();

        $this->postJson("/api/v1/clientes/{$cliente->id}/recordatorios-correo/{$envio->id}/reintentar")
            ->assertStatus(202)
            ->assertJsonPath('envio.estado', 'en_cola');

        $this->assertDatabaseHas('envios_correo', ['id' => $envio->id, 'estado' => 'en_cola', 'detalle_error' => null]);
        Queue::assertPushed(EnviarRecordatorioCobranzaJob::class);
    }

    public function test_no_se_puede_reintentar_uno_que_no_esta_fallido(): void
    {
        $this->actingAsAdmin();
        ['cliente' => $cliente, 'envio' => $envio] = $this->envioFallido();
        $envio->estado = EstadoEnvioCorreo::Enviado;
        $envio->save();

        $this->postJson("/api/v1/clientes/{$cliente->id}/recordatorios-correo/{$envio->id}/reintentar")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['envio']);
    }

    public function test_no_se_puede_reintentar_si_la_cuota_ya_se_pago(): void
    {
        $this->actingAsAdmin();
        ['cuota' => $cuota, 'cliente' => $cliente, 'envio' => $envio] = $this->envioFallido();
        $cuota->estado = EstadoCuota::Pagada;
        $cuota->save();

        $this->postJson("/api/v1/clientes/{$cliente->id}/recordatorios-correo/{$envio->id}/reintentar")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cuota_id']);

        $this->assertDatabaseHas('envios_correo', ['id' => $envio->id, 'estado' => 'fallido']);
    }

    public function test_un_envio_de_otra_empresa_responde_404(): void
    {
        $this->actingAsAdmin();
        ['cliente' => $cliente, 'envio' => $envio] = $this->envioFallido();

        $this->actingAsAdmin();

        $this->postJson("/api/v1/clientes/{$cliente->id}/recordatorios-correo/{$envio->id}/reintentar")->assertNotFound();
    }

    public function test_401_sin_sesion(): void
    {
        $this->actingAsAdmin();
        ['cliente' => $cliente, 'envio' => $envio] = $this->envioFallido();

        $this->salirDeSesion();
        $this->postJson("/api/v1/clientes/{$cliente->id}/recordatorios-correo/{$envio->id}/reintentar")->assertUnauthorized();
    }

    public function test_403_para_un_asesor_sin_permiso(): void
    {
        $admin = $this->actingAsAdmin();
        ['cliente' => $cliente, 'envio' => $envio] = $this->envioFallido();

        $this->actingAsAsesor(Empresa::findOrFail($admin->empresa_id))->update(['es_global' => true]);

        $this->postJson("/api/v1/clientes/{$cliente->id}/recordatorios-correo/{$envio->id}/reintentar")->assertForbidden();
    }
}
