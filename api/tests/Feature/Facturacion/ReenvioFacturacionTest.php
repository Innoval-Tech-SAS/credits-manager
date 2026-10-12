<?php

namespace Tests\Feature\Facturacion;

use App\Correo\Enums\TipoCorreo;
use App\Correo\Jobs\EnviarDocumentoFacturacionCorreoJob;
use App\Correo\Mail\CorreoDocumentoFacturacion;
use App\Empresas\Models\Empresa;
use App\Facturacion\Models\EnvioFacturacion;
use App\Facturacion\Services\EnvioFacturacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\Concerns\CreatesFacturacionFixtures;
use Tests\Concerns\SimulaCorreo;
use Tests\TestCase;

class ReenvioFacturacionTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use CreatesFacturacionFixtures;
    use RefreshDatabase;
    use SimulaCorreo;

    private const BASE = '/api/v1/facturacion';

    private function factura(string $correo = 'cliente@example.com'): array
    {
        $borrador = $this->borrador($correo);

        return $this->postJson(self::BASE.'/documentos/'.$borrador['id'].'/emitir')->assertOk()->json('documento');
    }

    private function borrador(string $correo = 'cliente@example.com'): array
    {
        $this->crearReglasFacturacion();
        $credito = $this->crearCredito($this->crearCliente(['correo' => $correo]));

        return $this->postJson(self::BASE.'/documentos', [
            'cliente_id' => $credito->cliente_id,
            'credito_id' => $credito->id,
            'lineas' => [['concepto' => 'INTERES.AMORT.ADM', 'descripcion' => 'Interés administrativo', 'valor' => '1000', 'tipo_concepto' => 'otros_gastos']],
        ])->assertStatus(201)->json('documento');
    }

    public function test_sin_autenticar_recibe_401(): void
    {
        $this->postJson(self::BASE.'/documentos/1/reenviar')->assertStatus(401);
    }

    public function test_asesor_sin_permiso_recibe_403(): void
    {
        $admin = $this->actingAsAdmin();
        $factura = $this->factura();
        $this->actingAsAsesor(Empresa::findOrFail($admin->empresa_id))->update(['es_global' => true]);

        $this->postJson(self::BASE.'/documentos/'.$factura['id'].'/reenviar')->assertStatus(403);
    }

    public function test_un_documento_de_otra_empresa_responde_404(): void
    {
        $this->actingAsAdmin();
        $factura = $this->factura();

        $this->actingAsAdmin();

        $this->postJson(self::BASE.'/documentos/'.$factura['id'].'/reenviar')->assertStatus(404);
    }

    public function test_sin_configuracion_de_correo_se_rechaza(): void
    {
        Queue::fake();
        $this->actingAsAdmin();
        $factura = $this->factura();

        $this->postJson(self::BASE.'/documentos/'.$factura['id'].'/reenviar')
            ->assertStatus(422)
            ->assertJsonValidationErrors('documento');

        Queue::assertNothingPushed();
    }

    public function test_sin_plantilla_predeterminada_de_facturacion_se_rechaza(): void
    {
        Queue::fake();
        $this->actingAsAdmin();
        $this->configuracionDeCorreo(TipoCorreo::Facturacion);
        $factura = $this->factura();

        $this->postJson(self::BASE.'/documentos/'.$factura['id'].'/reenviar')
            ->assertStatus(422)
            ->assertJsonValidationErrors('documento');

        Queue::assertNothingPushed();
    }

    /** Un tercero sin correo ya no llega a emitirse (el correo es un dato fiscal obligatorio): el rechazo ocurre antes del reenvío. */
    public function test_cliente_sin_correo_se_rechaza(): void
    {
        Queue::fake();
        $this->actingAsAdmin();
        $this->configuracionDeCorreo(TipoCorreo::Facturacion);
        $this->plantillaDeCorreo(TipoCorreo::Facturacion);
        $borrador = $this->borrador(correo: '');

        $this->postJson(self::BASE.'/documentos/'.$borrador['id'].'/emitir')
            ->assertStatus(422)
            ->assertJsonValidationErrors('documento');

        Queue::assertNothingPushed();
    }

    public function test_encola_el_envio_y_lo_registra(): void
    {
        Queue::fake();
        $this->actingAsAdmin();
        $this->configuracionDeCorreo(TipoCorreo::Facturacion);
        $this->plantillaDeCorreo(TipoCorreo::Facturacion);
        $factura = $this->factura('ana@example.com');

        $respuesta = $this->postJson(self::BASE.'/documentos/'.$factura['id'].'/reenviar');

        $respuesta->assertStatus(202)->assertJsonPath('envio.estado', 'en_cola');
        $this->assertDatabaseHas('envios_facturacion', [
            'documento_facturacion_id' => $factura['id'],
            'destinatario' => 'ana@example.com',
            'asunto' => "Factura {$factura['numero']}",
            'estado' => 'en_cola',
        ]);
        Queue::assertPushed(EnviarDocumentoFacturacionCorreoJob::class);
    }

    public function test_el_job_envia_el_pdf_adjunto_y_marca_el_envio_como_enviado(): void
    {
        Queue::fake();
        $fake = $this->simulaCorreo();
        $this->actingAsAdmin();
        $this->configuracionDeCorreo(TipoCorreo::Facturacion);
        $this->plantillaDeCorreo(TipoCorreo::Facturacion);
        $factura = $this->factura('ana@example.com');

        $this->postJson(self::BASE.'/documentos/'.$factura['id'].'/reenviar')->assertStatus(202);

        $envio = EnvioFacturacion::query()->firstOrFail();
        /** @var EnviarDocumentoFacturacionCorreoJob $job */
        [$job] = Queue::pushed(EnviarDocumentoFacturacionCorreoJob::class)->all();
        $job->handle(app(EnvioFacturacionService::class));

        $this->assertDatabaseHas('envios_facturacion', ['id' => $envio->id, 'estado' => 'enviado']);
        $this->assertNotNull(EnvioFacturacion::query()->find($envio->id)->enviado_en);
        $fake->assertSent(CorreoDocumentoFacturacion::class, fn (CorreoDocumentoFacturacion $correo) => $correo->destinatario === 'ana@example.com'
            && $correo->asunto === "Factura {$factura['numero']}"
            && $correo->pdfContenido !== '');
    }
}
