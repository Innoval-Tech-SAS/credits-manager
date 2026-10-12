<?php

namespace Tests\Feature\Permisos;

use App\Empresas\Models\Empresa;
use App\Usuarios\Models\Permiso;
use App\Usuarios\Models\Rol;
use App\Usuarios\Models\Usuario;
use Database\Seeders\PermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

/**
 * Los permisos genéricos que ya no controlan nada salen del catálogo, de los roles y de `tienePermiso` sin borrar
 * filas, y revertir la migración los devuelve con sus asignaciones intactas.
 */
class RetiroPermisosGenericosTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private const MIGRACION = 'migrations/2026_10_12_120000_retirar_permisos_genericos_sin_uso.php';

    private const RETIRADOS = ['contabilidad.listar', 'facturacion.listar', 'gastos.parametrizar', 'tributario.listar'];

    private const VIGENTE = 'contabilidad.listar_comprobantes';

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migracion()->down();
    }

    private function migracion(): object
    {
        return require database_path(self::MIGRACION);
    }

    private function permiso(string $clave): Permiso
    {
        [$modulo, $accion] = explode('.', $clave);

        return Permiso::query()->firstOrCreate(['modulo' => $modulo, 'accion' => $accion]);
    }

    /** Usuario de un rol con los permisos retirados y uno vigente, más una excepción concedida sobre un retirado. */
    private function usuarioConPermisosRetirados(): Usuario
    {
        $admin = $this->actingAsAdmin();
        $this->empresa = Empresa::findOrFail($admin->empresa_id);

        $rol = Rol::create(['empresa_id' => $this->empresa->id, 'nombre' => 'contable']);
        $rol->permisos()->sync(collect([...self::RETIRADOS, self::VIGENTE])->map(fn (string $clave) => $this->permiso($clave)->id)->all());

        $usuario = Usuario::create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Contable',
            'usuario' => 'contable_'.uniqid(),
            'password_hash' => 'irrelevante-en-tests',
            'rol_id' => $rol->id,
            'activo' => true,
        ]);
        $usuario->permisosExtra()->attach($this->permiso('informes_contables.listar')->id, ['concedido' => true]);

        return $usuario;
    }

    /** @return list<string> claves del catálogo que devuelve `GET /permisos` */
    private function catalogoDelPanel(): array
    {
        return collect($this->getJson('/api/v1/permisos')->assertOk()->json('permisos'))
            ->map(fn (array $permiso) => "{$permiso['modulo']}.{$permiso['accion']}")
            ->all();
    }

    public function test_los_retirados_salen_del_panel_del_rol_y_de_tiene_permiso_sin_borrar_filas(): void
    {
        $usuario = $this->usuarioConPermisosRetirados();
        $asignaciones = DB::table('rol_permiso')->where('rol_id', $usuario->rol_id)->count();

        $this->migracion()->up();

        $catalogo = $this->catalogoDelPanel();
        $usuario = $usuario->fresh();
        $delRol = $usuario->rol->permisos()->get()->map(fn (Permiso $permiso) => "{$permiso->modulo}.{$permiso->accion}")->all();

        foreach ([...self::RETIRADOS, 'informes_contables.listar'] as $clave) {
            [$modulo, $accion] = explode('.', $clave);

            $this->assertNotContains($clave, $catalogo, "{$clave} no debe salir en el panel de permisos.");
            $this->assertNotContains($clave, $delRol);
            $this->assertFalse($usuario->tienePermiso($modulo, $accion), "{$clave} ya no debe conceder nada.");
            $this->assertDatabaseHas('permisos', ['modulo' => $modulo, 'accion' => $accion]);
        }

        $this->assertContains(self::VIGENTE, $catalogo);
        $this->assertSame([self::VIGENTE], $delRol);
        $this->assertTrue($usuario->tienePermiso('contabilidad', 'listar_comprobantes'));
        $this->assertSame($asignaciones, DB::table('rol_permiso')->where('rol_id', $usuario->rol_id)->count(), 'Las asignaciones se conservan.');
    }

    public function test_ningun_permiso_retirado_esta_en_el_catalogo_vigente(): void
    {
        $vigentes = collect(PermisoSeeder::CATALOGO)
            ->flatMap(fn (array $acciones, string $modulo) => collect($acciones)->map(fn (string $accion) => "{$modulo}.{$accion}"));

        foreach ([...self::RETIRADOS, 'informes_contables.listar', 'configuracion_contable.editar', 'contabilidad.exportar'] as $clave) {
            $this->assertNotContains($clave, $vigentes);
        }
    }

    public function test_aplicarla_dos_veces_no_cambia_la_fecha_del_retiro(): void
    {
        $this->usuarioConPermisosRetirados();

        $this->migracion()->up();
        $primera = DB::table('permisos')->where(['modulo' => 'contabilidad', 'accion' => 'listar'])->value('borrado_en');
        $this->travel(1)->days();
        $this->migracion()->up();

        $this->assertNotNull($primera);
        $this->assertSame($primera, DB::table('permisos')->where(['modulo' => 'contabilidad', 'accion' => 'listar'])->value('borrado_en'));
    }

    public function test_revertirla_devuelve_los_permisos_con_sus_asignaciones(): void
    {
        $usuario = $this->usuarioConPermisosRetirados();

        $this->migracion()->up();
        $this->migracion()->down();

        $usuario = $usuario->fresh();
        $catalogo = $this->catalogoDelPanel();

        foreach ([...self::RETIRADOS, 'informes_contables.listar'] as $clave) {
            [$modulo, $accion] = explode('.', $clave);

            $this->assertContains($clave, $catalogo);
            $this->assertTrue($usuario->tienePermiso($modulo, $accion), "{$clave} vuelve con su asignación.");
        }
    }

    public function test_una_empresa_nueva_no_recibe_permisos_retirados(): void
    {
        $this->seed(PermisoSeeder::class);
        $this->migracion()->up();
        $this->actingAsSuperAdmin();

        $empresaId = $this->postJson('/api/v1/empresas', [
            'nombre_empresa' => 'Empresa limpia SAS', 'nit' => '900555444-1', 'digito_verificacion' => '1', 'direccion' => 'Calle 1',
            'municipio_id' => $this->municipioDePrueba()->id, 'telefono' => '3001112222', 'correo' => 'limpia@example.com',
            'admin_nombre' => 'Admin', 'admin_usuario' => 'admin_limpia', 'admin_password' => 'Clave123!',
        ])->assertCreated()->json('empresa.id');

        $asignadosAlAdmin = DB::table('rol_permiso')
            ->join('roles', 'roles.id', '=', 'rol_permiso.rol_id')
            ->join('permisos', 'permisos.id', '=', 'rol_permiso.permiso_id')
            ->where('roles.empresa_id', $empresaId)
            ->where('roles.nombre', 'admin')
            ->whereNotNull('permisos.borrado_en')
            ->count();

        $this->assertSame(0, $asignadosAlAdmin);
    }
}
