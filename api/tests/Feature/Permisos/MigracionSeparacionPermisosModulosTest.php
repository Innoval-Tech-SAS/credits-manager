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
 * La migración que separa `contabilidad` y `gastos` en módulos nuevos no debe quitar acceso a nadie:
 * para cada usuario, cada permiso viejo y su traducción nueva dan lo mismo antes y después.
 */
class MigracionSeparacionPermisosModulosTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private const MIGRACION = 'migrations/2026_10_05_100400_separar_permisos_contabilidad_en_modulos.php';

    /** Espejo de la migración: permiso vigente => permisos nuevos que dan la misma acción. */
    private const TRADUCCIONES = [
        'contabilidad.listar' => ['contabilidad.listar', 'configuracion_contable.listar', 'informes_contables.listar', 'terceros.listar'],
        'contabilidad.crear' => ['configuracion_contable.crear', 'terceros.crear'],
        'contabilidad.editar' => ['configuracion_contable.editar', 'terceros.editar'],
        'contabilidad.configurar' => ['configuracion_contable.configurar'],
        'contabilidad.cerrar_periodo' => ['configuracion_contable.cerrar_periodo'],
        'contabilidad.reabrir_periodo' => ['configuracion_contable.reabrir_periodo'],
        'contabilidad.exportar' => ['informes_contables.exportar'],
        'gastos.listar' => ['tributario.listar'],
        'gastos.parametrizar' => ['tributario.parametrizar'],
    ];

    private const MODULOS_NUEVOS = ['terceros', 'configuracion_contable', 'informes_contables', 'tributario'];

    private Empresa $empresa;

    /** @var array<string, Rol> */
    private array $roles = [];

    /** Parte del estado previo al retiro de los permisos genéricos, que esta migración todavía usa como origen. */
    protected function setUp(): void
    {
        parent::setUp();

        (require database_path('migrations/2026_10_12_120000_retirar_permisos_genericos_sin_uso.php'))->down();
    }

    private function migracion(): object
    {
        return require database_path(self::MIGRACION);
    }

    private function prepararRoles(): void
    {
        $this->empresa = $this->crearEmpresaDePrueba();

        foreach (['admin', 'contable', 'auditoria', 'asesor', 'consulta'] as $nombre) {
            $this->roles[$nombre] = Rol::create(['empresa_id' => $this->empresa->id, 'nombre' => $nombre]);
        }

        $asignaciones = [
            'admin' => ['contabilidad.listar', 'contabilidad.exportar', 'gastos.listar', 'gastos.parametrizar'],
            'contable' => [
                'contabilidad.listar', 'contabilidad.crear', 'contabilidad.editar', 'contabilidad.configurar',
                'contabilidad.cerrar_periodo', 'contabilidad.exportar', 'contabilidad.imprimir_comprobante',
                'gastos.listar', 'gastos.parametrizar',
            ],
            'auditoria' => ['contabilidad.listar', 'contabilidad.exportar', 'contabilidad.imprimir_comprobante'],
            'consulta' => ['contabilidad.listar', 'contabilidad.imprimir_comprobante'],
            'asesor' => ['clientes.listar', 'clientes.crear'],
        ];

        foreach ($asignaciones as $rol => $claves) {
            $ids = collect($claves)->map(fn (string $clave) => $this->permiso($clave)->id);
            $this->roles[$rol]->permisos()->sync($ids->all());
        }
    }

    private function permiso(string $clave): Permiso
    {
        [$modulo, $accion] = explode('.', $clave);

        return Permiso::query()->firstOrCreate(['modulo' => $modulo, 'accion' => $accion]);
    }

    private function crearUsuario(string $nombre, string $rol): Usuario
    {
        return Usuario::create([
            'empresa_id' => $this->empresa->id,
            'nombre' => $nombre,
            'usuario' => $nombre.'_'.uniqid(),
            'password_hash' => 'irrelevante-en-tests',
            'rol_id' => $this->roles[$rol]->id,
            'activo' => true,
        ]);
    }

    /** @return array<int, array<string, bool>> permiso "modulo.accion" => acceso, por id de usuario */
    private function matriz(array $usuarios): array
    {
        $claves = collect(self::TRADUCCIONES)->flatten()->merge(array_keys(self::TRADUCCIONES))->unique()->values();
        $matriz = [];

        foreach ($usuarios as $usuario) {
            foreach ($claves as $clave) {
                [$modulo, $accion] = explode('.', $clave);
                $matriz[$usuario->id][$clave] = $usuario->fresh()->tienePermiso($modulo, $accion);
            }
        }

        return $matriz;
    }

    public function test_la_migracion_conserva_el_acceso_de_cada_usuario_y_asesor_no_recibe_nada_nuevo(): void
    {
        $this->prepararRoles();

        $contable = $this->crearUsuario('Contable', 'contable');
        $auditoria = $this->crearUsuario('Auditoría', 'auditoria');
        $consulta = $this->crearUsuario('Consulta', 'consulta');
        $consulta->permisosExtra()->attach($this->permiso('contabilidad.listar')->id, ['concedido' => false]);
        $asesorConExcepcion = $this->crearUsuario('Asesor excepción', 'asesor');
        $asesorConExcepcion->permisosExtra()->attach($this->permiso('contabilidad.exportar')->id, ['concedido' => true]);
        $asesor = $this->crearUsuario('Asesor', 'asesor');
        $usuarios = [$contable, $auditoria, $consulta, $asesorConExcepcion, $asesor];

        $antes = $this->matriz($usuarios);

        $this->migracion()->up();

        $despues = $this->matriz($usuarios);

        foreach ($usuarios as $usuario) {
            foreach (self::TRADUCCIONES as $origen => $destinos) {
                $this->assertSame($antes[$usuario->id][$origen], $despues[$usuario->id][$origen], "{$usuario->nombre} perdió o ganó {$origen}.");

                foreach ($destinos as $destino) {
                    $this->assertSame(
                        $antes[$usuario->id][$origen],
                        $despues[$usuario->id][$destino],
                        "{$usuario->nombre}: {$destino} debe dar lo mismo que {$origen} antes de la migración.",
                    );
                }
            }
        }

        $this->assertFalse($despues[$asesor->id]['terceros.listar']);
        $this->assertFalse($despues[$asesor->id]['tributario.listar']);
        $this->assertTrue($despues[$asesorConExcepcion->id]['informes_contables.exportar']);
        $this->assertFalse($despues[$consulta->id]['configuracion_contable.listar'], 'La revocación por usuario se traduce y se mantiene.');
    }

    public function test_aplicarla_dos_veces_no_duplica_ni_cambia_accesos(): void
    {
        $this->prepararRoles();
        $contable = $this->crearUsuario('Contable', 'contable');

        $this->migracion()->up();
        $primera = $this->matriz([$contable]);

        $this->migracion()->up();

        $this->assertSame($primera, $this->matriz([$contable]));
        $this->assertSame(
            DB::table('rol_permiso')->where('rol_id', $this->roles['contable']->id)->count(),
            DB::table('rol_permiso')->where('rol_id', $this->roles['contable']->id)->distinct()->count('permiso_id'),
        );
    }

    public function test_el_admin_recibe_todo_el_catalogo_nuevo(): void
    {
        $this->prepararRoles();

        $this->migracion()->up();

        $permisosAdmin = DB::table('rol_permiso')
            ->join('permisos', 'permisos.id', '=', 'rol_permiso.permiso_id')
            ->where('rol_permiso.rol_id', $this->roles['admin']->id)
            ->get(['permisos.modulo', 'permisos.accion'])
            ->map(fn ($permiso) => "{$permiso->modulo}.{$permiso->accion}")
            ->all();

        foreach (self::TRADUCCIONES as $destinos) {
            foreach ($destinos as $destino) {
                $this->assertContains($destino, $permisosAdmin);
            }
        }
    }

    public function test_revertirla_quita_lo_agregado_y_conserva_el_acceso_anterior(): void
    {
        $this->prepararRoles();
        $contable = $this->crearUsuario('Contable', 'contable');
        $consulta = $this->crearUsuario('Consulta', 'consulta');
        $consulta->permisosExtra()->attach($this->permiso('contabilidad.listar')->id, ['concedido' => false]);
        $usuarios = [$contable, $consulta];

        $antes = $this->matriz($usuarios);

        $this->migracion()->up();
        $this->migracion()->down();

        $despues = $this->matriz($usuarios);

        foreach ($usuarios as $usuario) {
            foreach (array_keys(self::TRADUCCIONES) as $origen) {
                $this->assertSame($antes[$usuario->id][$origen], $despues[$usuario->id][$origen], "down() cambió {$origen}.");
            }
        }

        $this->assertSame(0, DB::table('permisos')->whereIn('modulo', self::MODULOS_NUEVOS)->count());
        $this->assertSame(0, DB::table('rol_permiso')->whereIn('permiso_id', DB::table('permisos')->whereIn('modulo', self::MODULOS_NUEVOS)->pluck('id'))->count());
    }
}
