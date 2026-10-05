<?php

namespace Tests\Feature\Facturacion;

use App\Creditos\Models\Credito;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class ConsecutivosFacturacionTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesAuditoriaFixtures;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    private const CONSECUTIVOS = '/api/v1/facturacion/consecutivos';

    private const DOCUMENTOS = '/api/v1/facturacion/documentos';

    private function factura(Credito $credito): array
    {
        $borrador = $this->postJson(self::DOCUMENTOS, [
            'credito_id' => $credito->id,
            'lineas' => [['concepto' => 'INTERES.AMORT.ADM', 'descripcion' => 'Interés', 'valor' => '100']],
        ])->assertStatus(201)->json('documento');

        return $this->postJson(self::DOCUMENTOS.'/'.$borrador['id'].'/emitir')->assertOk()->json('documento');
    }

    public function test_lista_los_tres_tipos_con_su_prefijo_inicial(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->getJson(self::CONSECUTIVOS)->assertOk();

        $this->assertSame(['factura', 'nota_credito', 'nota_debito'], array_column($respuesta->json('consecutivos'), 'tipo'));
        $this->assertSame('F', $respuesta->json('consecutivos.0.prefijo'));
        $this->assertSame(1, $respuesta->json('consecutivos.0.siguiente'));
    }

    public function test_cambia_el_prefijo_y_el_numero_inicial_antes_de_emitir(): void
    {
        $this->actingAsAdmin();

        $this->patchJson(self::CONSECUTIVOS.'/factura', ['prefijo' => 'FAC', 'numero_inicial' => 100])->assertOk();

        $factura = $this->factura($this->crearCredito($this->crearCliente()));

        $this->assertSame(100, $factura['consecutivo']);
        $this->assertSame('FAC 100', $factura['numero']);
    }

    public function test_no_cambia_el_numero_inicial_con_documentos_emitidos_pero_si_el_prefijo(): void
    {
        $this->actingAsAdmin();
        $this->factura($this->crearCredito($this->crearCliente()));

        $this->patchJson(self::CONSECUTIVOS.'/factura', ['numero_inicial' => 50])
            ->assertStatus(422)
            ->assertJsonValidationErrors('numero_inicial');

        $this->patchJson(self::CONSECUTIVOS.'/factura', ['prefijo' => 'NUEVA'])->assertOk();
        $this->assertDatabaseHas('consecutivos_documento', ['tipo_documento' => 'factura', 'prefijo' => 'NUEVA']);
    }

    public function test_rechaza_un_prefijo_con_caracteres_no_permitidos(): void
    {
        $this->actingAsAdmin();

        $this->patchJson(self::CONSECUTIVOS.'/factura', ['prefijo' => 'F-1'])->assertStatus(422)->assertJsonValidationErrors('prefijo');
    }

    public function test_sin_permiso_de_configurar_recibe_403(): void
    {
        $this->actingAsRolConPermisos('contable', ['facturacion.listar']);

        $this->getJson(self::CONSECUTIVOS)->assertOk();
        $this->patchJson(self::CONSECUTIVOS.'/factura', ['prefijo' => 'X'])->assertStatus(403);
    }

    public function test_sin_autenticar_recibe_401(): void
    {
        $this->getJson(self::CONSECUTIVOS)->assertStatus(401);
    }
}
