<?php

namespace Tests\Feature\Terceros;

use App\Terceros\Enums\TipoDocumentoTercero;
use App\Usuarios\Models\Permiso;
use App\Usuarios\Models\Rol;
use App\Usuarios\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class TerceroMultiRolTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    private function actingAsUsuarioConPermiso(string $modulo, string $accion): Usuario
    {
        $empresa = $this->crearEmpresaDePrueba();
        $rol = Rol::create(['empresa_id' => $empresa->id, 'nombre' => 'rol_test_'.uniqid()]);
        $permiso = Permiso::create(['modulo' => $modulo, 'accion' => $accion]);
        $rol->permisos()->attach($permiso->id);

        $usuario = Usuario::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Usuario de prueba',
            'usuario' => 'usuario_test_'.uniqid(),
            'password_hash' => 'irrelevante-en-tests',
            'rol_id' => $rol->id,
            'activo' => true,
        ]);

        $this->actingAs($usuario, 'sanctum');

        return $usuario;
    }

    public function test_un_mismo_documento_puede_ser_cliente_y_proveedor(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente(['numero_documento' => '900555555']);

        $respuesta = $this->postJson('/api/v1/proveedores', [
            'tipo_documento' => TipoDocumentoTercero::Cc->value,
            'numero_documento' => '900555555',
            'nombre' => 'Nombre distinto al del cliente',
        ]);

        $respuesta->assertStatus(201);
        $this->assertDatabaseCount('terceros', 1);
        $this->assertDatabaseHas('proveedores', ['tercero_id' => $cliente->tercero_id, 'activo' => true]);
    }

    public function test_filtra_terceros_por_tipo(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $this->crearTercero(); // "otro" sin extensión: no debe contar como cliente ni proveedor
        $this->postJson('/api/v1/proveedores', [
            'tipo_documento' => TipoDocumentoTercero::Nit->value,
            'numero_documento' => '900777777',
            'nombre' => 'Proveedor de prueba',
        ])->assertStatus(201);

        $respuesta = $this->getJson('/api/v1/terceros?tipo=cliente');

        $respuesta->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertSame($cliente->tercero_id, $respuesta->json('data.0.id'));
    }

    public function test_un_asesor_puede_listar_terceros_tipo_cliente(): void
    {
        $this->actingAsUsuarioConPermiso('clientes', 'listar');

        $respuesta = $this->getJson('/api/v1/terceros?tipo=cliente');

        $respuesta->assertStatus(200);
    }

    public function test_un_asesor_no_puede_listar_terceros_tipo_proveedor(): void
    {
        $this->actingAsUsuarioConPermiso('clientes', 'listar');

        $respuesta = $this->getJson('/api/v1/terceros?tipo=proveedor');

        $respuesta->assertStatus(403);
    }

    public function test_un_asesor_no_puede_listar_todos_los_terceros(): void
    {
        $this->actingAsUsuarioConPermiso('clientes', 'listar');

        $respuesta = $this->getJson('/api/v1/terceros');

        $respuesta->assertStatus(403);
    }
}
