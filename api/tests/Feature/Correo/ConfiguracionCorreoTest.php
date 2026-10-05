<?php

namespace Tests\Feature\Correo;

use App\Correo\Constants\CorreoConstants;
use App\Correo\Mail\CorreoCobranza;
use App\Correo\Models\ConfiguracionCorreo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\AfirmaAuditoria;
use Tests\Concerns\SimulaCorreo;
use Tests\TestCase;

class ConfiguracionCorreoTest extends TestCase
{
    use ActsAsAdmin;
    use AfirmaAuditoria;
    use RefreshDatabase;
    use SimulaCorreo;

    private function datosValidos(array $sobrescribir = []): array
    {
        return array_merge([
            'remitente' => 'cobranza@creditosdda.com',
            'nombre_remitente' => 'Cobranza DDA',
            'driver' => 'smtp',
            'host' => 'smtp.example.com',
            'puerto' => 587,
            'cifrado' => 'tls',
            'usuario' => 'cobranza@example.com',
            'clave' => 'secreto-de-prueba',
            'plantilla_asunto' => 'Recordatorio - {empresa}',
            'plantilla_cuerpo' => '<p>Hola {cliente}, la cuota {cuota} por {valor} vence el {fecha_vencimiento}.</p>',
        ], $sobrescribir);
    }

    public function test_admin_guarda_la_configuracion_y_la_clave_queda_cifrada_en_bd(): void
    {
        $admin = $this->actingAsAdmin();

        $respuesta = $this->putJson('/api/v1/correo/configuracion', $this->datosValidos());

        $respuesta->assertOk()
            ->assertJsonPath('configuracion.estado', 'configurada')
            ->assertJsonPath('configuracion.clave_estado', 'configurada');

        $this->assertDatabaseHas('configuraciones_correo', [
            'empresa_id' => $admin->empresa_id,
            'remitente' => 'cobranza@creditosdda.com',
            'driver' => 'smtp',
            'puerto' => 587,
        ]);

        $crudo = DB::table('configuraciones_correo')->where('empresa_id', $admin->empresa_id)->value('clave');
        $this->assertNotSame('secreto-de-prueba', $crudo);
        $this->assertSame('secreto-de-prueba', ConfiguracionCorreo::query()->first()->clave);
    }

    public function test_la_respuesta_nunca_incluye_la_clave(): void
    {
        $this->actingAsAdmin();

        $this->putJson('/api/v1/correo/configuracion', $this->datosValidos())
            ->assertOk()
            ->assertDontSee('secreto-de-prueba');

        $this->getJson('/api/v1/correo/configuracion')
            ->assertOk()
            ->assertJsonPath('configuracion.clave_estado', 'configurada')
            ->assertDontSee('secreto-de-prueba');
    }

    public function test_muestra_sin_configurar_cuando_la_empresa_no_tiene_fila(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/correo/configuracion')
            ->assertOk()
            ->assertJsonPath('configuracion.estado', 'sin_configurar')
            ->assertJsonPath('configuracion.clave_estado', 'sin_configurar')
            ->assertJsonPath('configuracion.plantilla.asunto', 'Recordatorio de pago - {empresa}')
            ->assertJsonPath('configuracion.plantilla.cuerpo', CorreoConstants::PLANTILLA_INICIAL_CUERPO)
            ->assertJsonCount(6, 'configuracion.variables');
    }

    public function test_guardar_sin_clave_conserva_la_clave_guardada(): void
    {
        $this->actingAsAdmin();

        $this->putJson('/api/v1/correo/configuracion', $this->datosValidos())->assertOk();
        $this->putJson('/api/v1/correo/configuracion', $this->datosValidos(['clave' => null, 'nombre_remitente' => 'Otro nombre']))->assertOk();

        $configuracion = ConfiguracionCorreo::query()->first();
        $this->assertSame('secreto-de-prueba', $configuracion->clave);
        $this->assertSame('Otro nombre', $configuracion->nombre_remitente);
    }

    public function test_la_plantilla_html_se_guarda_sin_scripts_ni_atributos_peligrosos(): void
    {
        $this->actingAsAdmin();

        $html = '<p>Hola {cliente}</p><script>alert(1)</script><a href="javascript:alert(1)" onclick="robar()">Pagar</a><iframe src="https://evil"></iframe>';

        $this->putJson('/api/v1/correo/configuracion', $this->datosValidos(['plantilla_cuerpo' => $html]))->assertOk();

        $guardada = ConfiguracionCorreo::query()->first()->plantilla_cuerpo;
        $this->assertStringContainsString('<p>Hola {cliente}</p>', $guardada);
        $this->assertStringContainsString('Pagar</a>', $guardada);
        $this->assertStringNotContainsString('<script', $guardada);
        $this->assertStringNotContainsString('<iframe', $guardada);
        $this->assertStringNotContainsString('onclick', $guardada);
        $this->assertStringNotContainsString('javascript:', $guardada);
    }

    public function test_422_si_faltan_campos_o_el_remitente_no_es_un_correo(): void
    {
        $this->actingAsAdmin();

        $this->putJson('/api/v1/correo/configuracion', $this->datosValidos(['remitente' => 'no-es-correo', 'host' => null, 'puerto' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['remitente', 'host', 'puerto']);
    }

    public function test_422_si_la_plantilla_usa_una_variable_que_no_existe(): void
    {
        $this->actingAsAdmin();

        $this->putJson('/api/v1/correo/configuracion', $this->datosValidos(['plantilla_cuerpo' => '<p>Hola {clente}</p>']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plantilla_cuerpo']);
    }

    public function test_401_sin_sesion(): void
    {
        $this->getJson('/api/v1/correo/configuracion')->assertUnauthorized();
        $this->putJson('/api/v1/correo/configuracion', $this->datosValidos())->assertUnauthorized();
    }

    public function test_403_para_un_asesor(): void
    {
        $this->actingAsAsesor();

        $this->getJson('/api/v1/correo/configuracion')->assertForbidden();
        $this->putJson('/api/v1/correo/configuracion', $this->datosValidos())->assertForbidden();
        $this->assertDatabaseCount('configuraciones_correo', 0);
    }

    public function test_guardar_registra_la_bitacora_sin_la_clave(): void
    {
        $this->actingAsAdmin();

        $this->putJson('/api/v1/correo/configuracion', $this->datosValidos())->assertOk();

        $evento = $this->assertEventoAuditoria('crear', 'correo', 'configuracion_correo');
        $this->assertStringNotContainsString('secreto-de-prueba', json_encode($evento));
    }

    public function test_cambiar_solo_la_clave_emite_evento_sin_mostrar_la_clave(): void
    {
        $this->actingAsAdmin();

        $this->putJson('/api/v1/correo/configuracion', $this->datosValidos())->assertOk();
        $this->putJson('/api/v1/correo/configuracion', $this->datosValidos(['clave' => 'clave-nueva-de-prueba']))->assertOk();

        $eventos = $this->eventosDeAuditoria('modificar', 'correo');
        $this->assertCount(1, $eventos);
        $this->assertStringContainsString('clave actualizada', $eventos[0]['descripcion']);
        $this->assertStringNotContainsString('clave-nueva-de-prueba', json_encode($eventos));
        $this->assertStringNotContainsString('secreto-de-prueba', json_encode($eventos));
    }

    public function test_prueba_envia_con_el_fake_y_devuelve_el_resultado_sin_credenciales(): void
    {
        $fake = $this->simulaCorreo();
        $this->actingAsAdmin();
        $this->putJson('/api/v1/correo/configuracion', $this->datosValidos())->assertOk();

        $respuesta = $this->postJson('/api/v1/correo/configuracion/prueba', ['destinatario' => 'destino@example.com']);

        $respuesta->assertOk()->assertJsonPath('enviado', true)->assertDontSee('secreto-de-prueba');
        $fake->assertSent(CorreoCobranza::class, fn (CorreoCobranza $correo) => $correo->destinatario === 'destino@example.com'
            && $correo->remitente === 'cobranza@creditosdda.com');
    }

    public function test_prueba_sin_configuracion_responde_422(): void
    {
        $this->simulaCorreo();
        $this->actingAsAdmin();

        $this->postJson('/api/v1/correo/configuracion/prueba', ['destinatario' => 'destino@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['destinatario']);
    }

    public function test_422_si_la_prueba_no_trae_un_destinatario_valido(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/correo/configuracion/prueba', ['destinatario' => 'no-es-correo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['destinatario']);
    }

    public function test_vista_previa_renderiza_html_con_datos_de_ejemplo_sin_guardar_ni_enviar(): void
    {
        $fake = $this->simulaCorreo();
        $this->actingAsAdmin();

        $respuesta = $this->postJson('/api/v1/correo/vista-previa', [
            'asunto' => 'Recordatorio - {empresa}',
            'cuerpo' => '<p>Estimado {cliente}, cuota {cuota}: <strong>{valor}</strong></p><script>alert(1)</script>',
        ]);

        $respuesta->assertOk()
            ->assertJsonPath('vista_previa.asunto', 'Recordatorio - Empresa de ejemplo')
            ->assertJsonPath('vista_previa.cuerpo', '<p>Estimado Juan Pérez, cuota 3: <strong>$ 350.000,00</strong></p>');
        $this->assertStringNotContainsString('<script', $respuesta->json('vista_previa.cuerpo'));
        $this->assertDatabaseCount('configuraciones_correo', 0);
        $fake->assertNothingSent();
    }

    public function test_422_en_la_vista_previa_con_variable_desconocida(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/correo/vista-previa', ['asunto' => 'Hola', 'cuerpo' => '<p>{valr}</p>'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cuerpo']);
    }
}
