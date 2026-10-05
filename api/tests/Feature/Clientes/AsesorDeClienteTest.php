<?php

namespace Tests\Feature\Clientes;

use App\Clientes\Models\Cliente;
use App\Clientes\Models\ClienteAsesorHistorial;
use App\Empresas\Models\Empresa;
use App\Terceros\Enums\TipoDocumentoTercero;
use App\Usuarios\Models\Rol;
use App\Usuarios\Models\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\AfirmaAuditoria;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\Concerns\ImportaArchivos;
use Tests\TestCase;

class AsesorDeClienteTest extends TestCase
{
    use ActsAsAdmin;
    use AfirmaAuditoria;
    use CreatesAuditoriaFixtures;
    use CreatesCreditFixtures;
    use ImportaArchivos;
    use RefreshDatabase;

    private function crearUsuarioConRol(string $nombreRol, Empresa $empresa, bool $activo = true): Usuario
    {
        $rol = Rol::query()->firstOrCreate(['empresa_id' => $empresa->id, 'nombre' => $nombreRol]);

        return Usuario::create([
            'empresa_id' => $empresa->id,
            'nombre' => ucfirst($nombreRol).' '.uniqid(),
            'usuario' => $nombreRol.'_'.uniqid(),
            'password_hash' => 'irrelevante-en-tests',
            'rol_id' => $rol->id,
            'activo' => $activo,
        ]);
    }

    private function payloadCliente(array $extra = []): array
    {
        return array_merge([
            'nombres' => 'Juan',
            'apellidos' => 'Pérez',
            'tipo_documento' => TipoDocumentoTercero::Cc->value,
            'numero_documento' => '1000000001',
            'direccion' => 'Calle 10 # 20-30',
            'barrio' => 'Centro',
            'ciudad' => 'Bogotá',
            'celular' => '3001234567',
            'correo' => 'juan@example.com',
            'empleador_id' => $this->crearEmpleador()->id,
        ], $extra);
    }

    public function test_sin_autenticar_no_crea_ni_reasigna_ni_ve_el_historial(): void
    {
        $cliente = $this->crearCliente(['asesor_id' => $this->actingAsAdmin()->id]);
        $this->salirDeSesion();

        $this->postJson('/api/v1/clientes', [])->assertStatus(401);
        $this->patchJson("/api/v1/clientes/{$cliente->id}/asesor", [])->assertStatus(401);
        $this->getJson("/api/v1/clientes/{$cliente->id}/asesor-historial")->assertStatus(401);
        $this->getJson('/api/v1/clientes/asesores-asignables')->assertStatus(401);
    }

    public function test_el_admin_debe_elegir_un_asesor_al_crear_un_cliente(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/clientes', $this->payloadCliente())
            ->assertStatus(422)->assertJsonValidationErrors('asesor_id');
        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_solo_se_asigna_a_un_asesor_o_admin_activo_de_la_misma_empresa(): void
    {
        $admin = $this->actingAsAdmin();
        $empresa = Empresa::findOrFail($admin->empresa_id);
        $otraEmpresa = $this->crearEmpresaDePrueba();

        $invalidos = [
            'de otra empresa' => $this->crearUsuarioConRol('asesor', $otraEmpresa)->id,
            'inactivo' => $this->crearUsuarioConRol('asesor', $empresa, activo: false)->id,
            'sin rol asignable' => $this->crearUsuarioConRol('consulta', $empresa)->id,
            'inexistente' => 999999,
        ];

        foreach ($invalidos as $caso => $asesorId) {
            $this->postJson('/api/v1/clientes', $this->payloadCliente(['asesor_id' => $asesorId]))
                ->assertStatus(422, "Debía rechazar el asesor {$caso}")->assertJsonValidationErrors('asesor_id');
        }
        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_el_admin_crea_un_cliente_con_asesor_y_queda_la_asignacion_inicial(): void
    {
        $admin = $this->actingAsAdmin();
        $asesor = $this->crearUsuarioConRol('asesor', Empresa::findOrFail($admin->empresa_id));

        $id = $this->postJson('/api/v1/clientes', $this->payloadCliente(['asesor_id' => $asesor->id]))
            ->assertCreated()->assertJsonPath('cliente.asesor.id', $asesor->id)->json('cliente.id');

        $this->assertDatabaseHas('clientes', ['id' => $id, 'asesor_id' => $asesor->id]);
        $this->assertDatabaseHas('cliente_asesor_historial', [
            'cliente_id' => $id, 'asesor_anterior_id' => null, 'asesor_id' => $asesor->id, 'asignado_por' => $admin->id,
        ]);
    }

    public function test_un_asesor_que_crea_un_cliente_queda_como_su_asesor_aunque_envie_otro(): void
    {
        $asesor = $this->actingAsRolConPermisos('asesor', ['clientes.crear']);
        $otro = $this->crearUsuarioConRol('asesor', Empresa::findOrFail($asesor->empresa_id));

        $id = $this->postJson('/api/v1/clientes', $this->payloadCliente(['asesor_id' => $otro->id]))
            ->assertCreated()->json('cliente.id');
        $this->assertDatabaseHas('clientes', ['id' => $id, 'asesor_id' => $asesor->id]);

        $id2 = $this->postJson('/api/v1/clientes', $this->payloadCliente(['numero_documento' => '1000000002', 'correo' => 'otro@example.com']))
            ->assertCreated()->json('cliente.id');
        $this->assertDatabaseHas('clientes', ['id' => $id2, 'asesor_id' => $asesor->id]);
    }

    public function test_reasignar_cambia_el_asesor_deja_historial_y_evento_de_bitacora(): void
    {
        $admin = $this->actingAsAdmin();
        $empresa = Empresa::findOrFail($admin->empresa_id);
        $primero = $this->crearUsuarioConRol('asesor', $empresa);
        $segundo = $this->crearUsuarioConRol('asesor', $empresa);
        $cliente = $this->crearCliente(['asesor_id' => $primero->id]);

        $this->patchJson("/api/v1/clientes/{$cliente->id}/asesor", ['asesor_id' => $segundo->id, 'motivo' => 'Renuncia del asesor'])
            ->assertOk()->assertJsonPath('cliente.asesor.id', $segundo->id);

        $this->assertDatabaseHas('clientes', ['id' => $cliente->id, 'asesor_id' => $segundo->id]);
        $this->assertDatabaseHas('cliente_asesor_historial', [
            'cliente_id' => $cliente->id, 'asesor_anterior_id' => $primero->id, 'asesor_id' => $segundo->id,
            'asignado_por' => $admin->id, 'motivo' => 'Renuncia del asesor',
        ]);

        $evento = $this->assertEventoAuditoria('modificar', 'clientes', 'cliente', $cliente->id);
        $this->assertSame('Renuncia del asesor', $evento['motivo']);
        $this->assertSame($primero->id, $evento['valores_anteriores']['asesor_id']);
        $this->assertSame($segundo->id, $evento['valores_nuevos']['asesor_id']);
    }

    public function test_reasignar_valida_el_motivo_y_rechaza_el_mismo_asesor_o_uno_no_asignable(): void
    {
        $admin = $this->actingAsAdmin();
        $empresa = Empresa::findOrFail($admin->empresa_id);
        $asesor = $this->crearUsuarioConRol('asesor', $empresa);
        $cliente = $this->crearCliente(['asesor_id' => $asesor->id]);
        $url = "/api/v1/clientes/{$cliente->id}/asesor";

        $this->patchJson($url, [])->assertStatus(422)->assertJsonValidationErrors(['asesor_id', 'motivo']);
        $this->patchJson($url, ['asesor_id' => $asesor->id, 'motivo' => 'x'])->assertStatus(422)->assertJsonValidationErrors('asesor_id');
        $this->patchJson($url, ['asesor_id' => $this->crearUsuarioConRol('consulta', $empresa)->id, 'motivo' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors('asesor_id');

        $this->assertDatabaseCount('cliente_asesor_historial', 0);
    }

    public function test_un_asesor_no_puede_reasignar_ni_ver_el_historial(): void
    {
        $asesor = $this->actingAsRolConPermisos('asesor', ['clientes.listar', 'clientes.ver', 'clientes.crear']);
        $cliente = $this->crearCliente(['asesor_id' => $asesor->id]);

        $this->patchJson("/api/v1/clientes/{$cliente->id}/asesor", ['asesor_id' => $asesor->id, 'motivo' => 'x'])->assertStatus(403);
        $this->getJson("/api/v1/clientes/{$cliente->id}/asesor-historial")->assertStatus(403);
    }

    public function test_el_historial_lista_las_asignaciones_de_la_mas_reciente_a_la_mas_antigua(): void
    {
        $admin = $this->actingAsAdmin();
        $empresa = Empresa::findOrFail($admin->empresa_id);
        $primero = $this->crearUsuarioConRol('asesor', $empresa);
        $segundo = $this->crearUsuarioConRol('asesor', $empresa);
        $asesorId = $primero->id;

        $id = $this->postJson('/api/v1/clientes', $this->payloadCliente(['asesor_id' => $asesorId]))->json('cliente.id');
        $this->patchJson("/api/v1/clientes/{$id}/asesor", ['asesor_id' => $segundo->id, 'motivo' => 'Cambio de zona'])->assertOk();

        $historial = $this->getJson("/api/v1/clientes/{$id}/asesor-historial")->assertOk()->json('historial');

        $this->assertCount(2, $historial);
        $this->assertSame($segundo->id, $historial[0]['asesor']['id']);
        $this->assertSame($primero->id, $historial[0]['asesor_anterior']['id']);
        $this->assertSame('Cambio de zona', $historial[0]['motivo']);
        $this->assertNull($historial[1]['asesor_anterior']);
        $this->assertSame($admin->id, $historial[0]['autor']['id']);
    }

    public function test_el_historial_es_de_solo_insercion(): void
    {
        $admin = $this->actingAsAdmin();
        $cliente = $this->crearCliente(['asesor_id' => $admin->id]);
        $fila = ClienteAsesorHistorial::create(['cliente_id' => $cliente->id, 'asesor_id' => $admin->id, 'asignado_por' => $admin->id]);

        try {
            $fila->update(['motivo' => 'alterado']);
            $this->fail('El modelo debía rechazar la modificación.');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(QueryException::class);
        DB::table('cliente_asesor_historial')->where('id', $fila->id)->update(['motivo' => 'alterado']);
    }

    public function test_el_historial_no_admite_borrado_en_la_base(): void
    {
        $admin = $this->actingAsAdmin();
        $cliente = $this->crearCliente(['asesor_id' => $admin->id]);
        $fila = ClienteAsesorHistorial::create(['cliente_id' => $cliente->id, 'asesor_id' => $admin->id, 'asignado_por' => $admin->id]);

        $this->expectException(QueryException::class);
        DB::table('cliente_asesor_historial')->where('id', $fila->id)->delete();
    }

    public function test_el_listado_de_asesores_asignables_solo_trae_los_de_la_propia_empresa(): void
    {
        $admin = $this->actingAsAdmin();
        $empresa = Empresa::findOrFail($admin->empresa_id);
        $propio = $this->crearUsuarioConRol('asesor', $empresa);
        $this->crearUsuarioConRol('asesor', $empresa, activo: false);
        $this->crearUsuarioConRol('consulta', $empresa);
        $ajeno = $this->crearUsuarioConRol('asesor', $this->crearEmpresaDePrueba());

        $ids = collect($this->getJson('/api/v1/clientes/asesores-asignables')->assertOk()->json('asesores'))->pluck('id');

        $this->assertEqualsCanonicalizing([$admin->id, $propio->id], $ids->all());
        $this->assertNotContains($ajeno->id, $ids->all());
    }

    public function test_sin_permiso_de_crear_clientes_no_lista_los_asesores_asignables(): void
    {
        $this->actingAsRolConPermisos('consulta', ['clientes.listar']);

        $this->getJson('/api/v1/clientes/asesores-asignables')->assertStatus(403);
    }

    public function test_otra_empresa_no_reasigna_ni_ve_el_historial_de_un_cliente_ajeno(): void
    {
        $adminA = $this->actingAsAdmin();
        $cliente = $this->crearCliente(['asesor_id' => $adminA->id]);

        $adminB = $this->actingAsAdmin();
        $asesorB = $this->crearUsuarioConRol('asesor', Empresa::findOrFail($adminB->empresa_id));

        $this->patchJson("/api/v1/clientes/{$cliente->id}/asesor", ['asesor_id' => $asesorB->id, 'motivo' => 'x'])->assertStatus(404);
        $this->getJson("/api/v1/clientes/{$cliente->id}/asesor-historial")->assertStatus(404);
        $this->assertDatabaseHas('clientes', ['id' => $cliente->id, 'asesor_id' => $adminA->id]);
    }

    public function test_no_se_reasigna_un_cliente_propio_a_un_asesor_de_otra_empresa(): void
    {
        $admin = $this->actingAsAdmin();
        $cliente = $this->crearCliente(['asesor_id' => $admin->id]);
        $ajeno = $this->crearUsuarioConRol('asesor', $this->crearEmpresaDePrueba());

        $this->patchJson("/api/v1/clientes/{$cliente->id}/asesor", ['asesor_id' => $ajeno->id, 'motivo' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors('asesor_id');
    }

    public function test_los_clientes_importados_quedan_a_nombre_de_quien_importa(): void
    {
        $admin = $this->actingAsAdmin();
        $this->prepararImportaciones();
        $csv = "tipo,tipo_persona,tipo_documento,numero_documento,nombres,apellidos,razon_social,digito_verificacion,representante_legal\n"
            ."cliente,natural,cc,1000000001,Ana,Torres,,,\n";

        $id = $this->importarCompleto('terceros', $csv);
        $this->assertSame(1, $this->importacion($id)->filas_aceptadas);

        $cliente = Cliente::query()->firstOrFail();
        $this->assertSame($admin->id, $cliente->asesor_id);
        $this->assertDatabaseHas('cliente_asesor_historial', ['cliente_id' => $cliente->id, 'asesor_id' => $admin->id, 'asesor_anterior_id' => null]);
    }
}
