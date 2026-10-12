<?php

namespace Tests\Feature\Permisos;

use App\Empresas\Models\Empresa;
use App\Usuarios\Models\Permiso;
use App\Usuarios\Models\Rol;
use App\Usuarios\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

/**
 * La separación por pantalla no cambia el acceso de nadie al desplegar: cada permiso nuevo da lo mismo que el
 * genérico del que sale, por rol y por excepción, y el admin recibe todos los nuevos.
 */
class MigracionPermisosPorPantallaTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private const MIGRACION = 'migrations/2026_10_12_110000_separar_permisos_por_pantalla.php';

    /** Muestra de la migración: genérico => uno de los permisos por pantalla que debe heredar. */
    private const MUESTRA = [
        'facturacion.listar' => ['facturacion.listar_facturas', 'facturacion.listar_notas_credito', 'facturacion.listar_notas_debito'],
        'contabilidad.listar' => ['contabilidad.listar_comprobantes', 'contabilidad.listar_cuentas_por_pagar'],
        'configuracion_contable.editar' => ['configuracion_contable.eliminar_centros_costo', 'configuracion_contable.editar_plan_cuentas'],
        'informes_contables.listar' => ['informes_contables.listar_caja_bancos', 'informes_contables.listar_conciliacion_apertura'],
        'tributario.parametrizar' => ['tributario.eliminar_valores_uvt', 'tributario.crear_tarifas_reteica'],
        'gastos.listar' => ['gastos.listar_gastos', 'gastos.listar_parametrizaciones'],
        'gastos.parametrizar' => ['gastos.crear_parametrizaciones', 'gastos.eliminar_tipos_gasto'],
    ];

    private Empresa $empresa;

    /** @var array<string, Rol> */
    private array $roles = [];

    /** Parte del estado previo: los genéricos vigentes (sin el retiro posterior) y sin los permisos por pantalla. */
    protected function setUp(): void
    {
        parent::setUp();

        (require database_path('migrations/2026_10_12_120000_retirar_permisos_genericos_sin_uso.php'))->down();
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

    private function prepararRoles(): void
    {
        $this->empresa = $this->crearEmpresaDePrueba();

        $asignaciones = [
            'admin' => [],
            'contable' => array_keys(self::MUESTRA),
            'consulta' => ['facturacion.listar', 'contabilidad.listar', 'informes_contables.listar', 'gastos.listar'],
            'asesor' => ['clientes.listar'],
        ];

        foreach ($asignaciones as $nombre => $claves) {
            $this->roles[$nombre] = Rol::create(['empresa_id' => $this->empresa->id, 'nombre' => $nombre]);
            $this->roles[$nombre]->permisos()->sync(collect($claves)->map(fn (string $clave) => $this->permiso($clave)->id)->all());
        }
    }

    private function crearUsuario(string $rol): Usuario
    {
        return Usuario::create([
            'empresa_id' => $this->empresa->id,
            'nombre' => ucfirst($rol),
            'usuario' => $rol.'_'.uniqid(),
            'password_hash' => 'irrelevante-en-tests',
            'rol_id' => $this->roles[$rol]->id,
            'activo' => true,
        ]);
    }

    /** @return list<string> */
    private function permisosDelRol(string $rol): array
    {
        return DB::table('rol_permiso')
            ->join('permisos', 'permisos.id', '=', 'rol_permiso.permiso_id')
            ->where('rol_permiso.rol_id', $this->roles[$rol]->id)
            ->orderBy('permisos.modulo')->orderBy('permisos.accion')
            ->get(['permisos.modulo', 'permisos.accion'])
            ->map(fn ($permiso) => "{$permiso->modulo}.{$permiso->accion}")
            ->all();
    }

    public function test_cada_permiso_por_pantalla_da_lo_mismo_que_su_generico_incluidas_las_excepciones(): void
    {
        $this->prepararRoles();
        $contable = $this->crearUsuario('contable');
        $consultaRevocada = $this->crearUsuario('consulta');
        $consultaRevocada->permisosExtra()->attach($this->permiso('facturacion.listar')->id, ['concedido' => false]);
        $asesorConExcepcion = $this->crearUsuario('asesor');
        $asesorConExcepcion->permisosExtra()->attach($this->permiso('gastos.listar')->id, ['concedido' => true]);
        $asesor = $this->crearUsuario('asesor');

        $this->migracion()->up();

        foreach ([$contable, $consultaRevocada, $asesorConExcepcion, $asesor] as $usuario) {
            $usuario = $usuario->fresh();

            foreach (self::MUESTRA as $origen => $destinos) {
                [$moduloOrigen, $accionOrigen] = explode('.', $origen);

                foreach ($destinos as $destino) {
                    [$modulo, $accion] = explode('.', $destino);

                    $this->assertSame(
                        $usuario->tienePermiso($moduloOrigen, $accionOrigen),
                        $usuario->tienePermiso($modulo, $accion),
                        "{$usuario->nombre}: {$destino} debe dar lo mismo que {$origen}.",
                    );
                }
            }
        }

        $this->assertFalse($consultaRevocada->fresh()->tienePermiso('facturacion', 'listar_notas_credito'), 'La revocación por usuario se traduce.');
        $this->assertTrue($asesorConExcepcion->fresh()->tienePermiso('gastos', 'listar_gastos'), 'La concesión por usuario se traduce.');
        $this->assertFalse($asesor->fresh()->tienePermiso('contabilidad', 'listar_comprobantes'), 'Quien no tenía el genérico no recibe nada.');
    }

    public function test_el_admin_recibe_todos_los_permisos_por_pantalla_y_los_demas_roles_solo_los_traducidos(): void
    {
        $this->prepararRoles();

        $this->migracion()->up();

        $admin = $this->permisosDelRol('admin');
        foreach (self::MUESTRA as $destinos) {
            foreach ($destinos as $destino) {
                $this->assertContains($destino, $admin);
            }
        }

        $consulta = $this->permisosDelRol('consulta');
        $this->assertContains('informes_contables.listar_libro_diario', $consulta);
        $this->assertNotContains('tributario.listar_valores_uvt', $consulta, 'Sin tributario.listar no recibe sus pantallas.');
        $this->assertNotContains('gastos.crear_tipos_gasto', $consulta, 'Leer no se traduce en escribir.');
        $this->assertSame(['clientes.listar'], $this->permisosDelRol('asesor'));
    }

    public function test_aplicarla_dos_veces_no_duplica_ni_cambia_asignaciones(): void
    {
        $this->prepararRoles();

        $this->migracion()->up();
        $primera = $this->permisosDelRol('contable');
        $this->migracion()->up();

        $this->assertSame($primera, $this->permisosDelRol('contable'));
    }

    public function test_revertirla_deja_las_asignaciones_como_estaban(): void
    {
        $this->prepararRoles();
        $antes = $this->permisosDelRol('contable');

        $this->migracion()->up();
        $this->migracion()->down();

        $this->assertSame($antes, $this->permisosDelRol('contable'));
        $this->assertSame(0, Permiso::query()->where(['modulo' => 'facturacion', 'accion' => 'listar_facturas'])->count());
    }
}
