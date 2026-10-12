<?php

namespace Tests\Feature\Empresas;

use App\Empresas\Models\Empresa;
use App\Pagos\Enums\FormaPago;
use App\Pagos\Models\Pago;
use App\Usuarios\Models\Permiso;
use App\Usuarios\Models\Rol;
use App\Usuarios\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class PermisosImpresionTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesAuditoriaFixtures;
    use CreatesContabilidadFixtures;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    /** Cada imprimible: [nombre, permiso de consulta que ya no basta, permiso propio de impresión]. */
    public static function imprimibles(): array
    {
        return [
            'estado_cuenta' => ['estado_cuenta', 'creditos.listar', 'creditos.imprimir_estado_cuenta'],
            'recibo' => ['recibo', 'pagos.listar', 'pagos.imprimir_recibo'],
            'comprobante' => ['comprobante', 'contabilidad.listar_comprobantes', 'contabilidad.imprimir_comprobante'],
            'reporte_empleador' => ['reporte_empleador', 'cartera.exportar', 'cartera.imprimir_reporte_empleador'],
        ];
    }

    /** Crea el registro imprimible como admin (el binding de ruta responde 404 antes que el permiso) y devuelve su ruta y su empresa. */
    private function rutaDeImprimible(string $imprimible): array
    {
        $empresa = Empresa::findOrFail($this->actingAsAdmin()->empresa_id);

        $ruta = match ($imprimible) {
            'estado_cuenta' => "/api/v1/creditos/{$this->crearCredito($this->crearCliente())->id}/estado-cuenta/pdf",
            'recibo' => "/api/v1/pagos/{$this->crearPago()->id}/recibo/pdf",
            'comprobante' => "/api/v1/contabilidad/comprobantes/{$this->crearComprobante()}/pdf",
            'reporte_empleador' => '/api/v1/cartera/reporte-empleador/pdf',
        };

        return [$ruta, $empresa];
    }

    private function crearPago(): Pago
    {
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente);

        return Pago::create([
            'credito_id' => $credito->id, 'cliente_id' => $cliente->id, 'valor_pago' => 300, 'fecha_pago' => '2026-02-01',
            'forma_pago' => FormaPago::Transferencia,
        ]);
    }

    private function crearComprobante(): int
    {
        $tipo = $this->crearTipoComprobante(['codigo' => 'CD']);
        $debito = $this->crearCuenta(['codigo' => '110505', 'nombre' => 'Caja general']);
        $credito = $this->crearCuenta(['codigo' => '413505', 'nombre' => 'Ingresos por intereses']);

        return $this->postJson('/api/v1/contabilidad/comprobantes', [
            'tipo_comprobante_id' => $tipo->id,
            'fecha' => '2026-01-15',
            'concepto' => 'Ajuste manual de prueba',
            'lineas' => [
                ['cuenta_id' => $debito->id, 'debito' => 100, 'credito' => 0],
                ['cuenta_id' => $credito->id, 'debito' => 0, 'credito' => 100],
            ],
        ])->assertCreated()->json('comprobante.id');
    }

    #[DataProvider('imprimibles')]
    public function test_consultar_o_exportar_no_implica_poder_imprimir(string $imprimible, string $permisoDeConsulta, string $permisoDeImpresion): void
    {
        $this->assertNotSame($permisoDeImpresion, $permisoDeConsulta);
        [$ruta, $empresa] = $this->rutaDeImprimible($imprimible);
        $this->actingAsRolConPermisos('rol_consulta', [$permisoDeConsulta], $empresa);

        $this->getJson($ruta)->assertStatus(403);
    }

    #[DataProvider('imprimibles')]
    public function test_el_permiso_propio_de_impresion_abre_la_ruta(string $imprimible, string $permisoDeConsulta, string $permisoDeImpresion): void
    {
        [$ruta, $empresa] = $this->rutaDeImprimible($imprimible);
        $this->actingAsRolConPermisos('rol_imprime', [$permisoDeImpresion], $empresa);

        $this->assertNotSame(403, $this->getJson($ruta)->getStatusCode());
    }

    public function test_ver_un_cliente_ya_no_exige_permiso_de_editar(): void
    {
        $this->actingAsRolConPermisos('rol_ve', ['clientes.ver']);
        $cliente = $this->crearCliente();

        $this->getJson("/api/v1/clientes/{$cliente->id}")->assertOk();
        $this->getJson("/api/v1/clientes/{$cliente->id}/resumen")->assertOk();
        $this->getJson("/api/v1/clientes/{$cliente->id}/documentos")->assertOk();
        $this->patchJson("/api/v1/clientes/{$cliente->id}", [])->assertStatus(403);
    }

    public function test_editar_un_cliente_no_implica_poder_verlo(): void
    {
        $this->actingAsRolConPermisos('rol_edita', ['clientes.editar']);
        $cliente = $this->crearCliente();

        $this->getJson("/api/v1/clientes/{$cliente->id}")->assertStatus(403);
    }

    public function test_la_migracion_concede_los_permisos_nuevos_a_quien_tenia_el_permiso_de_origen(): void
    {
        $this->actingAsRolConPermisos('rol_estados', ['creditos.listar']);
        $usuario = Usuario::query()->latest('id')->first();
        $rol = Rol::query()->findOrFail($usuario->rol_id);
        $conExcepcion = Usuario::create([
            'empresa_id' => $usuario->empresa_id, 'nombre' => 'Con excepción', 'usuario' => 'excepcion_'.uniqid(),
            'password_hash' => 'irrelevante-en-tests', 'rol_id' => $rol->id, 'activo' => true,
        ]);
        $conExcepcion->permisosExtra()->attach(Permiso::firstOrCreate(['modulo' => 'pagos', 'accion' => 'listar'])->id, ['concedido' => true]);

        (require database_path('migrations/2026_09_24_100000_agregar_permisos_impresion_y_ver_cliente.php'))->up();

        $this->assertTrue($rol->fresh()->permisos()->where(['modulo' => 'creditos', 'accion' => 'imprimir_estado_cuenta'])->exists());
        $this->assertFalse($rol->fresh()->permisos()->where(['modulo' => 'pagos', 'accion' => 'imprimir_recibo'])->exists());
        $this->assertTrue($conExcepcion->fresh()->tienePermiso('pagos', 'imprimir_recibo'));
        $this->assertFalse($conExcepcion->fresh()->tienePermiso('contabilidad', 'imprimir_comprobante'));
    }
}
