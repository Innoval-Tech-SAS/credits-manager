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
 * La migración de permisos granulares no cambia el acceso de nadie al desplegar: cada permiso nuevo da lo mismo que
 * el permiso del que se copia, y lo que antes exigía el rol admin solo llega a los roles admin.
 */
class MigracionPermisosGranularesTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private const MIGRACION = 'migrations/2026_10_12_100000_agregar_permisos_granulares_empleadores_clientes_facturacion.php';

    /** Espejo de la migración: permiso nuevo => permiso de origen. */
    private const COPIAS = [
        'empleadores.listar' => 'clientes.listar',
        'empleadores.ver' => 'clientes.ver',
        'empleadores.crear' => 'clientes.crear',
        'empleadores.editar' => 'clientes.editar',
        'facturacion.registrar_cobro' => 'facturacion.crear',
        'facturacion.reversar_cobro' => 'facturacion.anular',
    ];

    private const SOLO_ADMIN = ['empleadores.eliminar', 'clientes.eliminar', 'clientes.reasignar_asesor'];

    private Empresa $empresa;

    /** @var array<string, Rol> */
    private array $roles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->migracion()->down();
    }

    private function migracion(): object
    {
        return require database_path(self::MIGRACION);
    }

    private function prepararRoles(): void
    {
        $this->empresa = $this->crearEmpresaDePrueba();

        $asignaciones = [
            'admin' => ['clientes.listar', 'clientes.ver', 'clientes.crear', 'clientes.editar', 'facturacion.crear', 'facturacion.anular'],
            'asesor' => ['clientes.listar', 'clientes.ver', 'clientes.crear'],
            'cartera' => ['clientes.listar', 'clientes.ver', 'clientes.editar'],
            'contable' => ['clientes.listar', 'clientes.ver', 'facturacion.crear', 'facturacion.anular'],
            'auditoria' => ['cartera.listar'],
        ];

        foreach ($asignaciones as $nombre => $claves) {
            $this->roles[$nombre] = Rol::create(['empresa_id' => $this->empresa->id, 'nombre' => $nombre]);
            $this->roles[$nombre]->permisos()->sync(collect($claves)->map(fn (string $clave) => $this->permiso($clave)->id)->all());
        }
    }

    private function permiso(string $clave): Permiso
    {
        [$modulo, $accion] = explode('.', $clave);

        return Permiso::query()->firstOrCreate(['modulo' => $modulo, 'accion' => $accion]);
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

    /** @return array<string, list<string>> nombre de rol => permisos asignados en `rol_permiso` */
    private function permisosPorRol(): array
    {
        return collect($this->roles)->map(fn (Rol $rol) => DB::table('rol_permiso')
            ->join('permisos', 'permisos.id', '=', 'rol_permiso.permiso_id')
            ->where('rol_permiso.rol_id', $rol->id)
            ->orderBy('permisos.modulo')->orderBy('permisos.accion')
            ->get(['permisos.modulo', 'permisos.accion'])
            ->map(fn ($permiso) => "{$permiso->modulo}.{$permiso->accion}")
            ->all())->all();
    }

    public function test_cada_permiso_nuevo_da_lo_mismo_que_su_origen_incluidas_las_excepciones(): void
    {
        $this->prepararRoles();
        $cartera = $this->crearUsuario('cartera');
        $asesorRevocado = $this->crearUsuario('asesor');
        $asesorRevocado->permisosExtra()->attach($this->permiso('clientes.crear')->id, ['concedido' => false]);
        $auditorConExcepcion = $this->crearUsuario('auditoria');
        $auditorConExcepcion->permisosExtra()->attach($this->permiso('facturacion.crear')->id, ['concedido' => true]);
        $contable = $this->crearUsuario('contable');
        $usuarios = [$cartera, $asesorRevocado, $auditorConExcepcion, $contable];

        $this->migracion()->up();

        foreach ($usuarios as $usuario) {
            $usuario = $usuario->fresh();

            foreach (self::COPIAS as $nueva => $origen) {
                [$moduloNuevo, $accionNueva] = explode('.', $nueva);
                [$moduloOrigen, $accionOrigen] = explode('.', $origen);

                $this->assertSame(
                    $usuario->tienePermiso($moduloOrigen, $accionOrigen),
                    $usuario->tienePermiso($moduloNuevo, $accionNueva),
                    "{$usuario->nombre}: {$nueva} debe dar lo mismo que {$origen}.",
                );
            }
        }

        $this->assertFalse($asesorRevocado->fresh()->tienePermiso('empleadores', 'crear'), 'La revocación por usuario se traduce.');
        $this->assertTrue($auditorConExcepcion->fresh()->tienePermiso('facturacion', 'registrar_cobro'), 'La concesión por usuario se traduce.');
    }

    public function test_lo_que_era_solo_del_rol_admin_no_llega_a_otros_roles(): void
    {
        $this->prepararRoles();

        $this->migracion()->up();

        $permisos = $this->permisosPorRol();

        foreach (self::SOLO_ADMIN as $clave) {
            $this->assertContains($clave, $permisos['admin']);

            foreach (['asesor', 'cartera', 'contable', 'auditoria'] as $rol) {
                $this->assertNotContains($clave, $permisos[$rol], "El rol {$rol} no debe recibir {$clave}.");
            }
        }

        $this->assertNotContains('empleadores.listar', $permisos['auditoria'], 'Sin clientes.listar no recibe empleadores.listar.');
        $this->assertNotContains('facturacion.registrar_cobro', $permisos['cartera']);
    }

    public function test_aplicarla_dos_veces_no_duplica_ni_cambia_asignaciones(): void
    {
        $this->prepararRoles();

        $this->migracion()->up();
        $primera = $this->permisosPorRol();
        $this->migracion()->up();

        $this->assertSame($primera, $this->permisosPorRol());
    }

    public function test_revertirla_deja_las_asignaciones_como_estaban(): void
    {
        $this->prepararRoles();
        $antes = $this->permisosPorRol();

        $this->migracion()->up();
        $this->migracion()->down();

        $this->assertSame($antes, $this->permisosPorRol());
        $this->assertSame(0, Permiso::query()->where('modulo', 'empleadores')->count());
    }
}
