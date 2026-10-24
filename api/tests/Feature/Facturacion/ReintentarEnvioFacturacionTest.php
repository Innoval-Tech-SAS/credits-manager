<?php

namespace Tests\Feature\Facturacion;

use App\Correo\Enums\EstadoEnvioCorreo;
use App\Correo\Enums\TipoCorreo;
use App\Correo\Jobs\EnviarDocumentoFacturacionCorreoJob;
use App\Empresas\Models\Empresa;
use App\Facturacion\Models\EnvioFacturacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\Concerns\CreatesFacturacionFixtures;
use Tests\Concerns\SimulaCorreo;
use Tests\TestCase;

class ReintentarEnvioFacturacionTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use CreatesFacturacionFixtures;
    use RefreshDatabase;
    use SimulaCorreo;

    private const BASE = '/api/v1/facturacion';

    private function facturaConEnvioFallido(string $correo = 'ana@example.com'): array
    {
        Queue::fake();
        $this->crearReglasFacturacion();
        $this->configuracionDeCorreo(TipoCorreo::Facturacion);
        $this->plantillaDeCorreo(TipoCorreo::Facturacion);
        $credito = $this->crearCredito($this->crearCliente(['correo' => $correo]));

        $borrador = $this->postJson(self::BASE.'/documentos', [
            'cliente_id' => $credito->cliente_id,
            'credito_id' => $credito->id,
            'lineas' => [['concepto' => 'INTERES.AMORT.ADM', 'descripcion' => 'Interés administrativo', 'valor' => '1000', 'tipo_concepto' => 'otros_gastos']],
        ])->assertStatus(201)->json('documento');
        $factura = $this->postJson(self::BASE.'/documentos/'.$borrador['id'].'/emitir')->assertOk()->json('documento');

        $this->postJson(self::BASE.'/documentos/'.$factura['id'].'/reenviar')->assertStatus(202);

        $envio = EnvioFacturacion::query()->firstOrFail();
        $envio->estado = EstadoEnvioCorreo::Fallido;
        $envio->detalle_error = 'fallo de prueba';
        $envio->save();

        return ['factura' => $factura, 'envio' => $envio];
    }

    public function test_reintentar_un_envio_fallido_lo_vuelve_a_encolar(): void
    {
        $this->actingAsAdmin();
        ['factura' => $factura, 'envio' => $envio] = $this->facturaConEnvioFallido();

        $this->postJson(self::BASE."/documentos/{$factura['id']}/envios/{$envio->id}/reintentar")
            ->assertStatus(202)
            ->assertJsonPath('envio.estado', 'en_cola');

        $this->assertDatabaseHas('envios_facturacion', ['id' => $envio->id, 'estado' => 'en_cola', 'detalle_error' => null]);
        Queue::assertPushed(EnviarDocumentoFacturacionCorreoJob::class);
    }

    public function test_no_se_puede_reintentar_uno_que_no_esta_fallido(): void
    {
        $this->actingAsAdmin();
        ['factura' => $factura, 'envio' => $envio] = $this->facturaConEnvioFallido();
        $envio->estado = EstadoEnvioCorreo::Enviado;
        $envio->save();

        $this->postJson(self::BASE."/documentos/{$factura['id']}/envios/{$envio->id}/reintentar")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['envio']);
    }

    public function test_no_se_puede_reintentar_si_la_factura_ya_fue_anulada(): void
    {
        $this->actingAsAdmin();
        ['factura' => $factura, 'envio' => $envio] = $this->facturaConEnvioFallido();

        $this->postJson(self::BASE."/documentos/{$factura['id']}/anular", ['motivo' => 'Corrección de prueba'])->assertOk();

        $this->postJson(self::BASE."/documentos/{$factura['id']}/envios/{$envio->id}/reintentar")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['documento']);

        $this->assertDatabaseHas('envios_facturacion', ['id' => $envio->id, 'estado' => 'fallido']);
    }

    public function test_historial_lista_los_envios_del_documento(): void
    {
        $this->actingAsAdmin();
        ['factura' => $factura] = $this->facturaConEnvioFallido();

        $this->getJson(self::BASE."/documentos/{$factura['id']}/envios")
            ->assertOk()
            ->assertJsonCount(1, 'envios')
            ->assertJsonPath('envios.0.estado', 'fallido');
    }

    public function test_un_envio_de_otra_empresa_responde_404(): void
    {
        $this->actingAsAdmin();
        ['factura' => $factura, 'envio' => $envio] = $this->facturaConEnvioFallido();

        $this->actingAsAdmin();

        $this->postJson(self::BASE."/documentos/{$factura['id']}/envios/{$envio->id}/reintentar")->assertNotFound();
    }

    public function test_401_sin_sesion(): void
    {
        $this->actingAsAdmin();
        ['factura' => $factura, 'envio' => $envio] = $this->facturaConEnvioFallido();

        $this->salirDeSesion();
        $this->postJson(self::BASE."/documentos/{$factura['id']}/envios/{$envio->id}/reintentar")->assertUnauthorized();
    }

    public function test_403_para_un_asesor_sin_permiso(): void
    {
        $admin = $this->actingAsAdmin();
        ['factura' => $factura, 'envio' => $envio] = $this->facturaConEnvioFallido();

        $this->actingAsAsesor(Empresa::findOrFail($admin->empresa_id))->update(['es_asesor_global' => true]);

        $this->postJson(self::BASE."/documentos/{$factura['id']}/envios/{$envio->id}/reintentar")->assertForbidden();
    }
}
