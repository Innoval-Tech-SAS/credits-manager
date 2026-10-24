<?php

namespace Tests\Feature\Permisos;

use App\Empresas\Models\Empresa;
use App\Usuarios\Models\Rol;
use App\Usuarios\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\Concerns\CreatesFacturacionFixtures;
use Tests\TestCase;

/**
 * Operaciones que antes se autorizaban con el permiso de otro módulo (empleadores con `clientes.*`), uno genérico
 * (cobros de facturación con `facturacion.crear`/`anular`) o solo con el rol admin (eliminar cliente, reasignar
 * asesor, eliminar empleador). Cada una exige ahora su permiso propio y el permiso vecino ya no alcanza.
 */
class PermisosGranularesTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesAuditoriaFixtures;
    use CreatesCreditFixtures;
    use CreatesFacturacionFixtures;
    use RefreshDatabase;

    private Empresa $empresa;

    private function prepararEmpresa(): void
    {
        $admin = $this->actingAsAdmin();
        $this->empresa = Empresa::findOrFail($admin->empresa_id);
    }

    private function actuarCon(array $permisos): Usuario
    {
        return $this->actingAsRolConPermisos('rol_'.uniqid(), $permisos, $this->empresa);
    }

    private function crearAsesor(): Usuario
    {
        $rol = Rol::query()->firstOrCreate(['empresa_id' => $this->empresa->id, 'nombre' => 'asesor']);

        return Usuario::create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Asesor '.uniqid(),
            'usuario' => 'asesor_'.uniqid(),
            'password_hash' => 'irrelevante-en-tests',
            'rol_id' => $rol->id,
            'activo' => true,
        ]);
    }

    public function test_los_permisos_de_clientes_ya_no_dan_acceso_a_empleadores(): void
    {
        $this->prepararEmpresa();
        $empleador = $this->crearEmpleador();
        $this->actuarCon(['clientes.listar', 'clientes.ver', 'clientes.crear', 'clientes.editar', 'clientes.eliminar']);

        $this->getJson('/api/v1/empleadores')->assertForbidden();
        $this->getJson("/api/v1/empleadores/{$empleador->id}")->assertForbidden();
        $this->postJson('/api/v1/empleadores', ['nombre' => 'Nuevo'])->assertForbidden();
        $this->patchJson("/api/v1/empleadores/{$empleador->id}", ['nombre' => 'Otro'])->assertForbidden();
        $this->deleteJson("/api/v1/empleadores/{$empleador->id}")->assertForbidden();
    }

    public function test_leer_empleadores_no_permite_modificarlos(): void
    {
        $this->prepararEmpresa();
        $empleador = $this->crearEmpleador();
        $this->actuarCon(['empleadores.listar', 'empleadores.ver']);

        $this->getJson('/api/v1/empleadores')->assertOk();
        $this->getJson("/api/v1/empleadores/{$empleador->id}")->assertOk();
        $this->postJson('/api/v1/empleadores', ['nombre' => 'Nuevo'])->assertForbidden();
        $this->patchJson("/api/v1/empleadores/{$empleador->id}", ['nombre' => 'Otro'])->assertForbidden();
        $this->deleteJson("/api/v1/empleadores/{$empleador->id}")->assertForbidden();
    }

    public function test_cada_permiso_de_empleadores_habilita_solo_su_operacion(): void
    {
        $this->prepararEmpresa();
        $empleador = $this->crearEmpleador();

        $this->actuarCon(['empleadores.crear']);
        $this->postJson('/api/v1/empleadores', ['nombre' => 'Creado con permiso propio'])->assertCreated();
        $this->assertDatabaseHas('empleadores', ['nombre' => 'Creado con permiso propio', 'empresa_id' => $this->empresa->id]);

        $this->actuarCon(['empleadores.editar']);
        $this->patchJson("/api/v1/empleadores/{$empleador->id}", ['nombre' => 'Editado con permiso propio'])->assertOk();
        $this->assertDatabaseHas('empleadores', ['id' => $empleador->id, 'nombre' => 'Editado con permiso propio']);

        $this->actuarCon(['empleadores.eliminar']);
        $this->deleteJson("/api/v1/empleadores/{$empleador->id}")->assertNoContent();
        $this->assertSoftDeleted('empleadores', ['id' => $empleador->id]);
    }

    public function test_eliminar_cliente_exige_clientes_eliminar_y_no_el_rol_admin(): void
    {
        $this->prepararEmpresa();
        $cliente = $this->crearCliente();
        $otro = $this->crearCliente();

        $this->actuarCon(['clientes.listar', 'clientes.ver', 'clientes.editar']);
        $this->deleteJson("/api/v1/clientes/{$cliente->id}")->assertForbidden();

        $this->actuarCon(['clientes.eliminar']);
        $this->deleteJson("/api/v1/clientes/{$cliente->id}")->assertNoContent();
        $this->assertSoftDeleted('clientes', ['id' => $cliente->id]);
        $this->assertDatabaseHas('clientes', ['id' => $otro->id, 'borrado_en' => null]);
    }

    public function test_reasignar_asesor_y_su_historial_exigen_clientes_reasignar_asesor(): void
    {
        $this->prepararEmpresa();
        $primero = $this->crearAsesor();
        $segundo = $this->crearAsesor();
        $cliente = $this->crearCliente(['asesor_id' => $primero->id]);

        $this->actuarCon(['clientes.listar', 'clientes.ver', 'clientes.crear', 'clientes.editar']);
        $this->patchJson("/api/v1/clientes/{$cliente->id}/asesor", ['asesor_id' => $segundo->id, 'motivo' => 'Cambio'])->assertForbidden();
        $this->getJson("/api/v1/clientes/{$cliente->id}/asesor-historial")->assertForbidden();

        $this->actuarCon(['clientes.reasignar_asesor']);
        $this->patchJson("/api/v1/clientes/{$cliente->id}/asesor", ['asesor_id' => $segundo->id, 'motivo' => 'Cambio de zona'])->assertOk();
        $this->assertDatabaseHas('clientes', ['id' => $cliente->id, 'asesor_id' => $segundo->id]);
        $this->getJson("/api/v1/clientes/{$cliente->id}/asesor-historial")->assertOk();
    }

    public function test_el_catalogo_de_asesores_asignables_sirve_a_cada_operacion_que_lo_usa(): void
    {
        $this->prepararEmpresa();

        foreach (['clientes.crear', 'clientes.reasignar_asesor', 'metas.configurar'] as $permiso) {
            $this->actuarCon([$permiso]);
            $this->getJson('/api/v1/clientes/asesores-asignables')->assertOk();
        }

        $this->actuarCon(['clientes.listar', 'clientes.ver', 'clientes.editar', 'metas.listar']);
        $this->getJson('/api/v1/clientes/asesores-asignables')->assertForbidden();
    }

    public function test_crear_o_anular_facturas_no_basta_para_registrar_ni_reversar_cobros(): void
    {
        $this->prepararEmpresa();
        [$factura, $cobro] = $this->facturaConCobro();
        $this->actuarCon(['facturacion.listar_facturas', 'facturacion.crear', 'facturacion.anular']);

        $this->cobrar($factura)->assertForbidden();
        $this->postJson('/api/v1/facturacion/cobros', [])->assertForbidden();
        $this->postJson("/api/v1/facturacion/cobros/{$cobro['id']}/reversar", ['motivo' => 'Sin permiso'])->assertForbidden();
        $this->assertDatabaseCount('cobros_facturacion', 1);
    }

    public function test_los_permisos_de_cobro_registran_y_reversan_sin_permisos_de_factura(): void
    {
        $this->prepararEmpresa();
        [$factura, $cobro] = $this->facturaConCobro();

        $this->actuarCon(['facturacion.registrar_cobro']);
        $this->cobrar($factura, '100')->assertCreated();
        $this->postJson("/api/v1/facturacion/cobros/{$cobro['id']}/reversar", ['motivo' => 'Sin permiso'])->assertForbidden();
        $this->postJson('/api/v1/facturacion/documentos', [])->assertForbidden();

        $this->actuarCon(['facturacion.reversar_cobro']);
        $this->postJson("/api/v1/facturacion/cobros/{$cobro['id']}/reversar", ['motivo' => 'Consignación duplicada'])->assertOk();
        $this->cobrar($factura, '100')->assertForbidden();
    }

    /** Factura emitida de 1000 y un cobro de 400, creados como admin de la empresa. */
    private function facturaConCobro(): array
    {
        $this->crearReglasFacturacion();
        $credito = $this->crearCredito($this->crearCliente());

        $borrador = $this->postJson('/api/v1/facturacion/documentos', [
            'cliente_id' => $credito->cliente_id,
            'credito_id' => $credito->id,
            'lineas' => [['concepto' => 'INTERES.AMORT.ADM', 'descripcion' => 'Interés administrativo', 'valor' => '1000', 'tipo_concepto' => 'otros_gastos']],
        ])->assertCreated()->json('documento');

        $factura = $this->postJson("/api/v1/facturacion/documentos/{$borrador['id']}/emitir")->assertOk()->json('documento');

        return [$factura, $this->cobrar($factura)->assertCreated()->json('cobro')];
    }

    private function cobrar(array $factura, string $valor = '400'): TestResponse
    {
        return $this->postJson("/api/v1/facturacion/documentos/{$factura['id']}/cobros", [
            'fecha' => '2026-10-05',
            'valor' => $valor,
            'medio' => 'transferencia',
            'referencia' => 'REF-'.uniqid(),
        ]);
    }
}
