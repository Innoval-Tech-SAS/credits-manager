<?php

namespace Tests\Feature\Proveedores;

use App\Proveedores\Models\Proveedor;
use App\Terceros\Enums\TipoPersona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\Concerns\CreatesGastosFixtures;
use Tests\TestCase;

class AislamientoEntreEmpresasTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use CreatesGastosFixtures;
    use RefreshDatabase;

    private function crearProveedor(TipoPersona $tipoPersona = TipoPersona::Juridica): Proveedor
    {
        $tercero = $this->crearTercero(['tipo_persona' => $tipoPersona]);

        return Proveedor::create(['tercero_id' => $tercero->id, 'activo' => true]);
    }

    public function test_el_listado_no_muestra_proveedores_de_otra_empresa(): void
    {
        $this->actingAsAdmin();
        $this->crearProveedor();

        $this->actingAsAdmin();
        $propio = $this->crearProveedor();

        $respuesta = $this->getJson('/api/v1/proveedores');

        $respuesta->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertSame($propio->id, $respuesta->json('data.0.id'));
    }

    public function test_consultar_o_editar_un_proveedor_ajeno_devuelve_404(): void
    {
        $this->actingAsAdmin();
        $juridico = $this->crearProveedor();
        $natural = $this->crearProveedor(TipoPersona::Natural);

        $this->actingAsAdmin();

        $this->getJson("/api/v1/proveedores/{$juridico->id}")->assertStatus(404);
        $this->patchJson("/api/v1/proveedores/personas-juridicas/{$juridico->id}", ['razon_social' => 'Intento ajeno'])->assertStatus(404);
        $this->patchJson("/api/v1/proveedores/personas-naturales/{$natural->id}", ['nombres' => 'Intento'])->assertStatus(404);
    }

    public function test_crear_con_una_actividad_economica_de_otra_empresa_da_422(): void
    {
        $this->actingAsAdmin();
        $actividadAjena = $this->crearActividadEconomica();

        $this->actingAsAdmin();

        $this->postJson('/api/v1/proveedores/personas-naturales', [
            'tipo_documento' => 'cc', 'numero_documento' => '1000000001', 'nombres' => 'Ana', 'apellidos' => 'Torres',
            'actividad_economica_id' => $actividadAjena->id,
        ])->assertStatus(422)->assertJsonValidationErrors('actividad_economica_id');
        $this->assertDatabaseCount('proveedores', 0);
    }
}
