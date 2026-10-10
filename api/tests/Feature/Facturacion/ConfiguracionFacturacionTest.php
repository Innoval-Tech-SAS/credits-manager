<?php

namespace Tests\Feature\Facturacion;

use App\Empresas\Models\Empresa;
use App\Usuarios\Models\Rol;
use App\Usuarios\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\AfirmaAuditoria;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\TestCase;

class ConfiguracionFacturacionTest extends TestCase
{
    use ActsAsAdmin;
    use AfirmaAuditoria;
    use CreatesAuditoriaFixtures;
    use RefreshDatabase;

    private const BASE = '/api/v1/facturacion/configuracion';

    public function test_sin_autenticar_recibe_401(): void
    {
        $this->getJson(self::BASE)->assertStatus(401);
        $this->putJson(self::BASE, ['nota_debito_tope' => '500000'])->assertStatus(401);
    }

    public function test_asesor_sin_permiso_recibe_403(): void
    {
        $admin = $this->actingAsAdmin();
        $this->actingAsAsesor(Empresa::findOrFail($admin->empresa_id));

        $this->getJson(self::BASE)->assertStatus(403);
        $this->putJson(self::BASE, ['nota_debito_tope' => '500000'])->assertStatus(403);
    }

    public function test_muestra_el_tope_por_defecto_sin_configuracion_previa(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->getJson(self::BASE)->assertOk();

        $this->assertSame('1000000.00', $respuesta->json('parametros.nota_debito_tope'));
    }

    public function test_valida_el_valor_del_tope(): void
    {
        $this->actingAsAdmin();

        $this->putJson(self::BASE, ['nota_debito_tope' => '0'])->assertStatus(422)->assertJsonValidationErrors('nota_debito_tope');
        $this->putJson(self::BASE, ['nota_debito_tope' => 'abc'])->assertStatus(422)->assertJsonValidationErrors('nota_debito_tope');
        $this->putJson(self::BASE, [])->assertStatus(422)->assertJsonValidationErrors('nota_debito_tope');
    }

    public function test_guarda_el_nuevo_tope_y_lo_audita(): void
    {
        $this->actingAsAdmin();

        $this->putJson(self::BASE, ['nota_debito_tope' => '2500000'])->assertOk()
            ->assertJsonPath('parametros.nota_debito_tope', '2500000.00');

        $this->assertDatabaseHas('facturacion_configuracion', ['clave' => 'nota_debito_tope', 'valor' => '250000000']);

        $auditados = $this->eventosDeAuditoria('modificar', 'facturacion');
        $this->assertNotEmpty($auditados);
    }

    public function test_cada_empresa_ve_y_guarda_su_propio_tope(): void
    {
        $admin = $this->actingAsAdmin();
        $this->putJson(self::BASE, ['nota_debito_tope' => '3000000'])->assertOk();

        $this->actingAsAdmin();
        $respuestaAjena = $this->getJson(self::BASE)->assertOk();
        $this->assertSame('1000000.00', $respuestaAjena->json('parametros.nota_debito_tope'));

        $this->actingAs($admin, 'sanctum');
        $respuestaOriginal = $this->getJson(self::BASE)->assertOk();
        $this->assertSame('3000000.00', $respuestaOriginal->json('parametros.nota_debito_tope'));
    }

    public function test_contable_con_el_permiso_puede_configurar(): void
    {
        $admin = $this->actingAsAdmin();

        $rol = Rol::create(['empresa_id' => $admin->empresa_id, 'nombre' => 'contable']);
        $contable = Usuario::create([
            'empresa_id' => $admin->empresa_id,
            'nombre' => 'Contable de prueba',
            'usuario' => 'contable_test_'.uniqid(),
            'password_hash' => 'irrelevante-en-tests',
            'rol_id' => $rol->id,
            'activo' => true,
        ]);
        foreach (['listar', 'configurar'] as $accion) {
            DB::table('rol_permiso')->insert([
                'rol_id' => $rol->id,
                'permiso_id' => DB::table('permisos')->where('modulo', 'facturacion')->where('accion', $accion)->value('id'),
                'creado_en' => now(),
                'ultima_actualizacion' => now(),
            ]);
        }

        $this->actingAs($contable, 'sanctum');

        $this->putJson(self::BASE, ['nota_debito_tope' => '750000'])->assertOk();
    }
}
