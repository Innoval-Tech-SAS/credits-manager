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
 * La separación por reporte no cambia el acceso de nadie al desplegar: cada permiso de exportar por reporte da lo mismo
 * que el `<modulo>.exportar` del que sale, por rol y por excepción; el admin recibe todos y los genéricos se retiran.
 */
class MigracionPermisosExportacionPorReporteTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private const MIGRACION = 'migrations/2026_10_12_140000_separar_permisos_exportacion_por_reporte.php';

    /** Muestra de la migración: genérico => permisos por reporte que debe heredar. */
    private const MUESTRA = [
        'clientes.exportar' => ['clientes.exportar_listado'],
        'creditos.exportar' => ['creditos.exportar_listado', 'creditos.exportar_estado_cuenta'],
        'cartera.exportar' => ['cartera.exportar_listado', 'cartera.exportar_reporte_empleador'],
        'gastos.exportar' => ['gastos.exportar_gastos', 'gastos.exportar_gastos_centro_costo'],
        'informes_contables.exportar' => ['informes_contables.exportar_libro_mayor', 'informes_contables.exportar_caja_bancos'],
        'auditoria.exportar' => ['auditoria.exportar_bitacora'],
    ];

    private Empresa $empresa;

    /** @var array<string, Rol> */
    private array $roles = [];

    /** Parte del estado previo: los `exportar` genéricos vigentes y sin los permisos por reporte. */
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

    private function prepararRoles(): void
    {
        $this->empresa = $this->crearEmpresaDePrueba();

        $asignaciones = [
            'admin' => [],
            'contable' => ['informes_contables.exportar', 'gastos.exportar', 'cartera.exportar', 'clientes.listar'],
            'auditoria' => ['auditoria.exportar', 'informes_contables.exportar'],
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

    /** @return list<string> permisos vigentes del rol */
    private function permisosDelRol(string $rol): array
    {
        return DB::table('rol_permiso')
            ->join('permisos', 'permisos.id', '=', 'rol_permiso.permiso_id')
            ->where('rol_permiso.rol_id', $this->roles[$rol]->id)
            ->whereNull('permisos.borrado_en')
            ->orderBy('permisos.modulo')->orderBy('permisos.accion')
            ->get(['permisos.modulo', 'permisos.accion'])
            ->map(fn ($permiso) => "{$permiso->modulo}.{$permiso->accion}")
            ->all();
    }

    public function test_cada_permiso_por_reporte_da_lo_mismo_que_su_generico_incluidas_las_excepciones(): void
    {
        $this->prepararRoles();
        $contable = $this->crearUsuario('contable');
        $contableRevocado = $this->crearUsuario('contable');
        $contableRevocado->permisosExtra()->attach($this->permiso('cartera.exportar')->id, ['concedido' => false]);
        $asesorConExcepcion = $this->crearUsuario('asesor');
        $asesorConExcepcion->permisosExtra()->attach($this->permiso('clientes.exportar')->id, ['concedido' => true]);
        $asesor = $this->crearUsuario('asesor');
        $usuarios = [$contable, $contableRevocado, $asesorConExcepcion, $asesor];

        $antes = [];
        foreach ($usuarios as $usuario) {
            foreach (array_keys(self::MUESTRA) as $origen) {
                [$modulo, $accion] = explode('.', $origen);
                $antes[$usuario->id][$origen] = $usuario->tienePermiso($modulo, $accion);
            }
        }

        $this->migracion()->up();

        foreach ($usuarios as $usuario) {
            $usuario = $usuario->fresh();

            foreach (self::MUESTRA as $origen => $destinos) {
                foreach ($destinos as $destino) {
                    [$modulo, $accion] = explode('.', $destino);
                    $this->assertSame($antes[$usuario->id][$origen], $usuario->tienePermiso($modulo, $accion), "{$usuario->nombre}: {$destino} debe dar lo mismo que {$origen}.");
                }
            }
        }

        $this->assertFalse($contableRevocado->fresh()->tienePermiso('cartera', 'exportar_reporte_empleador'), 'La revocación por usuario se traduce.');
        $this->assertTrue($asesorConExcepcion->fresh()->tienePermiso('clientes', 'exportar_listado'), 'La concesión por usuario se traduce.');
        $this->assertFalse($asesor->fresh()->tienePermiso('clientes', 'exportar_listado'), 'Quien no tenía el genérico no recibe nada.');
    }

    public function test_el_admin_recibe_todos_y_los_genericos_quedan_retirados(): void
    {
        $this->prepararRoles();
        $contable = $this->crearUsuario('contable');

        $this->migracion()->up();

        $admin = $this->permisosDelRol('admin');
        foreach (self::MUESTRA as $destinos) {
            foreach ($destinos as $destino) {
                $this->assertContains($destino, $admin);
            }
        }

        $this->assertNotContains('clientes.exportar_listado', $this->permisosDelRol('contable'), 'Exportar cartera no da exportar clientes.');
        $this->assertSame(['clientes.listar'], $this->permisosDelRol('asesor'));
        $this->assertFalse($contable->fresh()->tienePermiso('informes_contables', 'exportar'));
        $this->assertSame(0, Permiso::query()->where('accion', 'exportar')->whereIn('modulo', ['clientes', 'creditos', 'cartera', 'pagos', 'gastos', 'informes_contables', 'auditoria'])->count());
    }

    public function test_aplicarla_dos_veces_no_duplica_ni_cambia_asignaciones(): void
    {
        $this->prepararRoles();
        $revocado = $this->crearUsuario('contable');
        $revocado->permisosExtra()->attach($this->permiso('gastos.exportar')->id, ['concedido' => false]);

        $this->migracion()->up();
        $primera = $this->permisosDelRol('contable');
        $excepciones = DB::table('usuario_permiso')->count();
        $this->migracion()->up();

        $this->assertSame($primera, $this->permisosDelRol('contable'));
        $this->assertSame($excepciones, DB::table('usuario_permiso')->count());
    }

    public function test_revertirla_deja_las_asignaciones_como_estaban(): void
    {
        $this->prepararRoles();
        $contable = $this->crearUsuario('contable');
        $antes = $this->permisosDelRol('contable');

        $this->migracion()->up();
        $this->migracion()->down();

        $this->assertSame($antes, $this->permisosDelRol('contable'));
        $this->assertTrue($contable->fresh()->tienePermiso('informes_contables', 'exportar'));
        $this->assertSame(0, Permiso::withTrashed()->where(['modulo' => 'clientes', 'accion' => 'exportar_listado'])->count());
    }
}
