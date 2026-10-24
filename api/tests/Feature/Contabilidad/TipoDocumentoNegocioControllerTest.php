<?php

namespace Tests\Feature\Contabilidad;

use App\Contabilidad\Models\TipoDocumentoNegocio;
use App\Empresas\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class TipoDocumentoNegocioControllerTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    public function test_lista_los_6_tipos_sembrados_por_defecto(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->getJson('/api/v1/contabilidad/tipos-documento-negocio');

        $respuesta->assertStatus(200)->assertJsonCount(6, 'tipos');
        $this->assertSame(
            ['CC', 'CE', 'FV', 'NC', 'ND', 'RC'],
            collect($respuesta->json('tipos'))->pluck('codigo')->sort()->values()->all(),
        );
    }

    public function test_sin_autenticar_no_puede_listar(): void
    {
        $this->getJson('/api/v1/contabilidad/tipos-documento-negocio')->assertStatus(401);
    }

    public function test_los_valores_por_defecto_de_fv_reflejan_el_comportamiento_real(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->getJson('/api/v1/contabilidad/tipos-documento-negocio');

        $fv = collect($respuesta->json('tipos'))->firstWhere('codigo', 'FV');
        $this->assertSame('ingreso', $fv['naturaleza']);
        $this->assertTrue($fv['crea_saldo']);
        $this->assertFalse($fv['cruza_saldo']);

        $rc = collect($respuesta->json('tipos'))->firstWhere('codigo', 'RC');
        $this->assertFalse($rc['crea_saldo']);
        $this->assertTrue($rc['cruza_saldo']);
    }

    public function test_edita_nombre_y_flags_de_un_tipo(): void
    {
        $this->actingAsAdmin();
        $tipo = TipoDocumentoNegocio::where('codigo', 'ND')->first();

        $respuesta = $this->patchJson("/api/v1/contabilidad/tipos-documento-negocio/{$tipo->id}", [
            'nombre' => 'Nota débito (renombrada)',
            'activo' => false,
        ]);

        $respuesta->assertStatus(200);
        $this->assertDatabaseHas('tipos_documento_negocio', [
            'id' => $tipo->id,
            'nombre' => 'Nota débito (renombrada)',
            'activo' => false,
        ]);
    }

    public function test_un_tipo_inexistente_responde_404_al_editar(): void
    {
        $this->actingAsAdmin();

        $this->patchJson('/api/v1/contabilidad/tipos-documento-negocio/999999', ['nombre' => 'X'])->assertStatus(404);
    }

    public function test_sin_permiso_no_puede_editar(): void
    {
        $admin = $this->actingAsAdmin();
        $tipo = TipoDocumentoNegocio::where('empresa_id', $admin->empresa_id)->where('codigo', 'FV')->first();
        $this->actingAsAsesor(Empresa::find($admin->empresa_id));

        $this->patchJson("/api/v1/contabilidad/tipos-documento-negocio/{$tipo->id}", ['nombre' => 'X'])->assertStatus(403);
    }

    public function test_un_admin_no_ve_tipos_de_otra_empresa_en_el_listado(): void
    {
        $this->actingAsAdmin();

        $this->actingAsAdmin();
        $respuesta = $this->getJson('/api/v1/contabilidad/tipos-documento-negocio');

        $respuesta->assertStatus(200)->assertJsonCount(6, 'tipos');
    }

    public function test_un_admin_no_puede_editar_un_tipo_de_otra_empresa(): void
    {
        $this->actingAsAdmin();
        $tipoAjeno = TipoDocumentoNegocio::where('codigo', 'FV')->first();

        $this->actingAsAdmin();

        $respuesta = $this->patchJson("/api/v1/contabilidad/tipos-documento-negocio/{$tipoAjeno->id}", ['nombre' => 'Intento de edición ajena']);

        $respuesta->assertStatus(404);
    }
}
