<?php

namespace Tests\Feature\Facturacion;

use App\Terceros\Enums\TipoPersona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\Concerns\CreatesFacturacionFixtures;
use Tests\TestCase;

/** 8.2: no se puede emitir con datos fiscales del tercero incompletos; el borrador sí se puede guardar a medias. */
class TerceroFiscalCompletoFacturacionTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use CreatesFacturacionFixtures;
    use RefreshDatabase;

    private const BASE = '/api/v1/facturacion/documentos';

    private function lineas(): array
    {
        return [['concepto' => 'INTERES.AMORT.ADM', 'descripcion' => 'Interés administrativo', 'valor' => '1000', 'tipo_concepto' => 'otros_gastos']];
    }

    public function test_no_se_puede_emitir_si_al_tercero_le_falta_direccion(): void
    {
        $this->actingAsAdmin();
        $this->crearReglasFacturacion();
        $credito = $this->crearCredito($this->crearCliente(['direccion' => '']));

        $borrador = $this->postJson(self::BASE, ['cliente_id' => $credito->cliente_id, 'credito_id' => $credito->id, 'lineas' => $this->lineas()])
            ->assertStatus(201)
            ->json('documento');

        $this->postJson(self::BASE.'/'.$borrador['id'].'/emitir')->assertStatus(422)->assertJsonValidationErrors('documento');
        $this->assertDatabaseHas('documentos_facturacion', ['id' => $borrador['id'], 'estado' => 'borrador']);
    }

    public function test_no_se_puede_emitir_si_al_tercero_le_falta_correo(): void
    {
        $this->actingAsAdmin();
        $this->crearReglasFacturacion();
        $credito = $this->crearCredito($this->crearCliente(['correo' => '']));

        $borrador = $this->postJson(self::BASE, ['cliente_id' => $credito->cliente_id, 'credito_id' => $credito->id, 'lineas' => $this->lineas()])
            ->assertStatus(201)
            ->json('documento');

        $this->postJson(self::BASE.'/'.$borrador['id'].'/emitir')->assertStatus(422)->assertJsonValidationErrors('documento');
    }

    public function test_una_persona_juridica_sin_digito_de_verificacion_no_se_puede_emitir(): void
    {
        $this->actingAsAdmin();
        $this->crearReglasFacturacion();
        $credito = $this->crearCredito($this->crearCliente(['tipo_persona' => TipoPersona::Juridica]));
        $credito->cliente->tercero->update(['digito_verificacion' => null]);

        $borrador = $this->postJson(self::BASE, ['cliente_id' => $credito->cliente_id, 'credito_id' => $credito->id, 'lineas' => $this->lineas()])
            ->assertStatus(201)
            ->json('documento');

        $this->postJson(self::BASE.'/'.$borrador['id'].'/emitir')->assertStatus(422)->assertJsonValidationErrors('documento');
    }

    public function test_con_todos_los_datos_fiscales_completos_se_emite(): void
    {
        $this->actingAsAdmin();
        $this->crearReglasFacturacion();
        $credito = $this->crearCredito($this->crearCliente());

        $borrador = $this->postJson(self::BASE, ['cliente_id' => $credito->cliente_id, 'credito_id' => $credito->id, 'lineas' => $this->lineas()])
            ->assertStatus(201)
            ->json('documento');

        $this->postJson(self::BASE.'/'.$borrador['id'].'/emitir')->assertOk()->assertJsonPath('documento.estado', 'emitido');
    }
}
