<?php

namespace Tests\Feature\Correo;

use App\Clientes\Models\Cliente;
use App\Correo\Constants\CorreoConstants;
use App\Correo\Enums\EstadoEnvioCorreo;
use App\Correo\Enums\TipoCorreo;
use App\Correo\Jobs\EnviarRecordatorioCobranzaJob;
use App\Correo\Mail\CorreoCobranza;
use App\Correo\Models\EnvioCorreo;
use App\Correo\Services\EnvioRecordatorioService;
use App\Correo\Services\FabricaMailerCobranza;
use App\Creditos\Enums\EstadoCuota;
use App\Creditos\Models\Cuota;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\Concerns\SimulaCorreo;
use Tests\TestCase;

class RecordatorioCorreoTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use RefreshDatabase;
    use SimulaCorreo;

    /** @return array{cliente: Cliente, cuota: Cuota} */
    private function clienteConCuota(array $cuota = [], array $cliente = []): array
    {
        $clienteModelo = $this->crearCliente(array_merge(['correo' => 'juan@example.com', 'nombre' => 'Juan Pérez'], $cliente));
        $credito = $this->crearCredito($clienteModelo);
        $cuotaModelo = $this->crearCuota($credito, array_merge(['numero_cuota' => 3, 'valor' => 350000], $cuota));

        return ['cliente' => $clienteModelo, 'cuota' => $cuotaModelo];
    }

    public function test_encola_el_recordatorio_y_lo_registra_en_envios(): void
    {
        Queue::fake();
        $this->actingAsAdmin();
        $this->configuracionDeCorreo();
        $plantilla = $this->plantillaDeCorreo();
        ['cliente' => $cliente, 'cuota' => $cuota] = $this->clienteConCuota();

        $respuesta = $this->postJson("/api/v1/clientes/{$cliente->id}/recordatorios-correo", ['cuota_id' => $cuota->id]);

        $respuesta->assertStatus(202)->assertJsonPath('envio.estado', 'en_cola');
        $this->assertDatabaseHas('envios_correo', [
            'cliente_id' => $cliente->id,
            'cuota_id' => $cuota->id,
            'destinatario' => 'juan@example.com',
            'plantilla_correo_id' => $plantilla->id,
            'estado' => 'en_cola',
        ]);
        Queue::assertPushed(EnviarRecordatorioCobranzaJob::class);
    }

    public function test_el_job_envia_el_html_renderizado_y_marca_el_envio_como_enviado(): void
    {
        Queue::fake();
        $fake = $this->simulaCorreo();
        $admin = $this->actingAsAdmin();
        $this->configuracionDeCorreo();
        $this->plantillaDeCorreo();
        ['cliente' => $cliente, 'cuota' => $cuota] = $this->clienteConCuota();
        $this->postJson("/api/v1/clientes/{$cliente->id}/recordatorios-correo", ['cuota_id' => $cuota->id])->assertStatus(202);

        $envio = EnvioCorreo::query()->firstOrFail();
        (new EnviarRecordatorioCobranzaJob($envio->id, (int) $admin->empresa_id))->handle(app(EnvioRecordatorioService::class));

        $this->assertDatabaseHas('envios_correo', ['id' => $envio->id, 'estado' => 'enviado']);
        $this->assertNotNull(EnvioCorreo::query()->find($envio->id)->enviado_en);
        $fake->assertSent(CorreoCobranza::class, fn (CorreoCobranza $correo) => $correo->destinatario === 'juan@example.com'
            && str_contains($correo->cuerpoHtml, 'Estimado(a) <strong>Juan Pérez</strong>')
            && str_contains($correo->cuerpoHtml, '$ 350.000,00')
            && str_contains($correo->cuerpoHtml, '>3<')
            && str_contains($correo->cuerpoHtml, '01/02/2026'));
    }

    public function test_un_fallo_de_smtp_deja_el_envio_fallido_sin_el_detalle_del_servidor(): void
    {
        Queue::fake();
        $admin = $this->actingAsAdmin();
        $this->configuracionDeCorreo();
        $this->plantillaDeCorreo();
        ['cliente' => $cliente, 'cuota' => $cuota] = $this->clienteConCuota();
        $this->postJson("/api/v1/clientes/{$cliente->id}/recordatorios-correo", ['cuota_id' => $cuota->id])->assertStatus(202);

        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('send')->andThrow(new TransportException('Authentication failed for cobranza@example.com'));
        $this->app->instance(FabricaMailerCobranza::class, $this->fabricaQueEntrega($mailer));

        $envio = EnvioCorreo::query()->firstOrFail();
        (new EnviarRecordatorioCobranzaJob($envio->id, (int) $admin->empresa_id))->handle(app(EnvioRecordatorioService::class));

        $envio->refresh();
        $this->assertSame(EstadoEnvioCorreo::Fallido, $envio->estado);
        $this->assertSame(CorreoConstants::MENSAJE_SMTP_FALLIDO, $envio->detalle_error);
        $this->assertStringNotContainsString('cobranza@example.com', $envio->detalle_error);
    }

    public function test_el_job_no_reenvia_un_envio_que_ya_no_esta_en_cola(): void
    {
        Queue::fake();
        $fake = $this->simulaCorreo();
        $admin = $this->actingAsAdmin();
        $this->configuracionDeCorreo();
        $this->plantillaDeCorreo();
        ['cliente' => $cliente, 'cuota' => $cuota] = $this->clienteConCuota();
        $this->postJson("/api/v1/clientes/{$cliente->id}/recordatorios-correo", ['cuota_id' => $cuota->id])->assertStatus(202);

        $envio = EnvioCorreo::query()->firstOrFail();
        $envio->estado = EstadoEnvioCorreo::Enviado;
        $envio->save();

        (new EnviarRecordatorioCobranzaJob($envio->id, (int) $admin->empresa_id))->handle(app(EnvioRecordatorioService::class));

        $fake->assertNothingSent();
    }

    public function test_422_si_la_cuota_ya_esta_pagada(): void
    {
        Queue::fake();
        $this->actingAsAdmin();
        $this->configuracionDeCorreo();
        $this->plantillaDeCorreo();
        ['cliente' => $cliente, 'cuota' => $cuota] = $this->clienteConCuota(['estado' => EstadoCuota::Pagada]);

        $this->postJson("/api/v1/clientes/{$cliente->id}/recordatorios-correo", ['cuota_id' => $cuota->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cuota_id']);
        $this->assertDatabaseCount('envios_correo', 0);
    }

    public function test_422_si_la_cuota_es_de_otro_cliente(): void
    {
        Queue::fake();
        $this->actingAsAdmin();
        $this->configuracionDeCorreo();
        $this->plantillaDeCorreo();
        ['cliente' => $clienteA] = $this->clienteConCuota();
        ['cuota' => $cuotaDeB] = $this->clienteConCuota(['numero_cuota' => 1], ['correo' => 'otro@example.com']);

        $this->postJson("/api/v1/clientes/{$clienteA->id}/recordatorios-correo", ['cuota_id' => $cuotaDeB->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cuota_id']);
    }

    public function test_422_si_el_cliente_no_tiene_correo(): void
    {
        Queue::fake();
        $this->actingAsAdmin();
        $this->configuracionDeCorreo();
        $this->plantillaDeCorreo();
        ['cliente' => $cliente, 'cuota' => $cuota] = $this->clienteConCuota(cliente: ['correo' => null]);

        $this->postJson("/api/v1/clientes/{$cliente->id}/recordatorios-correo", ['cuota_id' => $cuota->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cuota_id']);
    }

    public function test_422_si_la_empresa_no_tiene_configurado_el_correo(): void
    {
        Queue::fake();
        $this->actingAsAdmin();
        ['cliente' => $cliente, 'cuota' => $cuota] = $this->clienteConCuota();

        $this->postJson("/api/v1/clientes/{$cliente->id}/recordatorios-correo", ['cuota_id' => $cuota->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cuota_id']);
    }

    public function test_422_si_no_hay_plantilla_predeterminada(): void
    {
        Queue::fake();
        $this->actingAsAdmin();
        $this->configuracionDeCorreo();
        ['cliente' => $cliente, 'cuota' => $cuota] = $this->clienteConCuota();

        $this->postJson("/api/v1/clientes/{$cliente->id}/recordatorios-correo", ['cuota_id' => $cuota->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cuota_id']);
    }

    public function test_422_si_falta_la_cuota(): void
    {
        $this->actingAsAdmin();
        ['cliente' => $cliente] = $this->clienteConCuota();

        $this->postJson("/api/v1/clientes/{$cliente->id}/recordatorios-correo", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cuota_id']);
    }

    public function test_404_si_el_cliente_no_existe(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/clientes/999999/recordatorios-correo', ['cuota_id' => 1])->assertNotFound();
    }

    public function test_401_sin_sesion(): void
    {
        $this->actingAsAdmin();
        ['cliente' => $cliente, 'cuota' => $cuota] = $this->clienteConCuota();

        $this->salirDeSesion();
        $this->postJson("/api/v1/clientes/{$cliente->id}/recordatorios-correo", ['cuota_id' => $cuota->id])->assertUnauthorized();
    }

    public function test_403_para_un_asesor_sin_permiso_de_cartera(): void
    {
        Queue::fake();
        $this->actingAsAsesor();
        $this->configuracionDeCorreo();
        ['cliente' => $cliente, 'cuota' => $cuota] = $this->clienteConCuota();

        $this->postJson("/api/v1/clientes/{$cliente->id}/recordatorios-correo", ['cuota_id' => $cuota->id])->assertForbidden();
        $this->assertDatabaseCount('envios_correo', 0);
    }

    public function test_vista_previa_del_cliente_usa_la_plantilla_elegida(): void
    {
        $this->simulaCorreo();
        $this->actingAsAdmin();
        $this->configuracionDeCorreo();
        $plantilla = $this->plantillaDeCorreo(TipoCorreo::Cobranza, [
            'asunto' => 'Cuota {cuota} - {empresa}',
            'cuerpo' => '<p>{cliente}: {valor} vence {fecha_vencimiento}</p>',
        ]);
        ['cliente' => $cliente, 'cuota' => $cuota] = $this->clienteConCuota();

        $this->postJson("/api/v1/clientes/{$cliente->id}/recordatorios-correo/vista-previa", [
            'cuota_id' => $cuota->id,
            'plantilla_correo_id' => $plantilla->id,
        ])
            ->assertOk()
            ->assertJsonPath('vista_previa.destinatario', 'juan@example.com')
            ->assertJsonPath('vista_previa.cuerpo', '<p>Juan Pérez: $ 350.000,00 vence '.$cuota->fecha_vencimiento->format('d/m/Y').'</p>');
        $this->assertDatabaseCount('envios_correo', 0);
    }

    public function test_vista_previa_sin_plantilla_usa_la_predeterminada_de_la_empresa(): void
    {
        $this->simulaCorreo();
        $this->actingAsAdmin();
        $this->configuracionDeCorreo();
        $this->plantillaDeCorreo(TipoCorreo::Cobranza, ['asunto' => 'Cobro {cuota}', 'cuerpo' => '<p>{cliente} debe {valor}</p>']);
        ['cliente' => $cliente, 'cuota' => $cuota] = $this->clienteConCuota();

        $this->postJson("/api/v1/clientes/{$cliente->id}/recordatorios-correo/vista-previa", ['cuota_id' => $cuota->id])
            ->assertOk()
            ->assertJsonPath('vista_previa.asunto', 'Cobro 3')
            ->assertJsonPath('vista_previa.cuerpo', '<p>Juan Pérez debe $ 350.000,00</p>');
    }

    public function test_historial_lista_envios_y_solo_cuotas_por_cobrar(): void
    {
        Queue::fake();
        $this->actingAsAdmin();
        $this->configuracionDeCorreo();
        $this->plantillaDeCorreo();
        ['cliente' => $cliente, 'cuota' => $cuota] = $this->clienteConCuota();
        $this->crearCuota($cuota->credito, ['numero_cuota' => 2, 'estado' => EstadoCuota::Pagada]);
        $this->postJson("/api/v1/clientes/{$cliente->id}/recordatorios-correo", ['cuota_id' => $cuota->id])->assertStatus(202);

        $this->getJson("/api/v1/clientes/{$cliente->id}/recordatorios-correo")
            ->assertOk()
            ->assertJsonCount(1, 'envios')
            ->assertJsonPath('envios.0.estado', 'en_cola')
            ->assertJsonCount(1, 'cuotas_cobrables')
            ->assertJsonPath('cuotas_cobrables.0.numero_cuota', 3)
            ->assertJsonPath('cuotas_cobrables.0.valor', '350000.00');
    }
}
