<?php

namespace Tests\Unit\Auditoria;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Auditoria\Services\RespaldoAuditoriaService;
use App\Usuarios\Models\Rol;
use App\Usuarios\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\AfirmaAuditoria;
use Tests\Concerns\UsaRespaldoAuditoriaTemporal;
use Tests\TestCase;

class EmisorAuditoriaHelpersTest extends TestCase
{
    use ActsAsAdmin;
    use AfirmaAuditoria;
    use RefreshDatabase;
    use UsaRespaldoAuditoriaTemporal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararRespaldoTemporal();
    }

    protected function tearDown(): void
    {
        $this->limpiarRespaldoTemporal();

        parent::tearDown();
    }

    private function emisor(): EmisorAuditoria
    {
        return app(EmisorAuditoria::class);
    }

    private function usuario(): Usuario
    {
        $admin = $this->actingAsAdmin();

        return Usuario::create([
            'empresa_id' => $admin->empresa_id, 'nombre' => 'Ana', 'usuario' => 'ana_'.uniqid(), 'password_hash' => 'secreta-123', 'rol_id' => $admin->rol_id, 'activo' => true,
        ]);
    }

    public function test_un_evento_sin_sesion_lleva_el_empresa_id_explicito_y_ningun_autor(): void
    {
        $empresa = $this->crearEmpresaDePrueba();

        $this->emisor()->registrar(AccionAuditoria::Modificar, 'cartera', 'Proceso automático', empresaId: $empresa->id);

        $evento = $this->eventosDeAuditoria()[0];
        $this->assertSame($empresa->id, $evento['empresa_id']);
        $this->assertNull($evento['autor']['usuario_id']);
    }

    public function test_sin_empresa_explicita_el_evento_lleva_la_del_autor(): void
    {
        $admin = $this->actingAsAdmin();

        $this->emisor()->registrar(AccionAuditoria::Modificar, 'cartera', 'Con sesión');

        $this->assertSame($admin->empresa_id, $this->eventosDeAuditoria()[0]['empresa_id']);
    }

    public function test_la_instantanea_omite_las_columnas_de_tiempo_y_los_campos_ocultos(): void
    {
        $usuario = $this->usuario();

        $instantanea = $this->emisor()->instantanea($usuario);

        $this->assertSame('Ana', $instantanea['nombre']);
        foreach ([...AuditoriaConstants::COLUMNAS_TIEMPO, 'password_hash'] as $columna) {
            $this->assertArrayNotHasKey($columna, $instantanea);
        }
    }

    public function test_registrar_creacion_incluye_los_datos_adicionales_y_nunca_la_clave(): void
    {
        $usuario = $this->usuario();

        $this->emisor()->registrarCreacion('usuarios', 'Usuario creado', $usuario, ['origen' => 'prueba']);

        $evento = $this->eventosDeAuditoria('crear')[0];
        $this->assertSame('usuario', $evento['entidad']);
        $this->assertSame($usuario->id, $evento['entidad_id']);
        $this->assertSame('prueba', $evento['valores_nuevos']['origen']);
        $this->assertStringNotContainsString('secreta-123', json_encode($evento));
    }

    public function test_registrar_modificacion_guarda_solo_lo_que_cambio(): void
    {
        $usuario = $this->usuario();
        $anteriores = $this->emisor()->instantanea($usuario);

        $usuario->update(['nombre' => 'Ana María']);
        $this->emisor()->registrarModificacion('usuarios', 'Usuario modificado', $usuario, $anteriores, 'porque sí');

        $evento = $this->eventosDeAuditoria('modificar')[0];
        $this->assertSame(['nombre' => 'Ana'], $evento['valores_anteriores']);
        $this->assertSame(['nombre' => 'Ana María'], $evento['valores_nuevos']);
        $this->assertSame('porque sí', $evento['motivo']);
    }

    public function test_registrar_modificacion_sin_cambios_no_emite(): void
    {
        $usuario = $this->usuario();
        $anteriores = $this->emisor()->instantanea($usuario);

        $usuario->update(['nombre' => 'Ana']);
        $this->emisor()->registrarModificacion('usuarios', 'Usuario modificado', $usuario, $anteriores);

        $this->assertSinEventosDeAuditoria();
    }

    public function test_un_numero_como_texto_no_cuenta_como_cambio(): void
    {
        $usuario = $this->usuario();
        $anteriores = $this->emisor()->instantanea($usuario);

        $usuario->rol_id = (string) $usuario->rol_id;
        $this->emisor()->registrarModificacion('usuarios', 'Usuario modificado', $usuario, $anteriores);

        $this->assertSinEventosDeAuditoria();
    }

    public function test_un_dato_adicional_nuevo_cuenta_como_cambio_aunque_sea_falso(): void
    {
        $usuario = $this->usuario();
        $anteriores = $this->emisor()->instantanea($usuario);

        $this->emisor()->registrarModificacion('usuarios', 'Usuario modificado', $usuario, $anteriores, adicionales: ['contabilizado' => false]);

        $evento = $this->eventosDeAuditoria('modificar')[0];
        $this->assertSame(['contabilizado' => false], $evento['valores_nuevos']);
        $this->assertSame([], $evento['valores_anteriores'] ?? []);
    }

    public function test_registrar_eliminacion_guarda_el_ultimo_estado(): void
    {
        $usuario = $this->usuario();

        $this->emisor()->registrarEliminacion('usuarios', 'Usuario eliminado', $usuario, 'baja');

        $evento = $this->eventosDeAuditoria('eliminar')[0];
        $this->assertSame($usuario->usuario, $evento['valores_anteriores']['usuario']);
        $this->assertNull($evento['valores_nuevos']);
        $this->assertSame('baja', $evento['motivo']);
    }

    public function test_el_nombre_de_la_entidad_sale_de_la_clase_en_snake_case(): void
    {
        $this->actingAsAdmin();
        $rol = Rol::create(['nombre' => 'cobranza']);

        $this->emisor()->registrarCreacion('usuarios', 'Rol creado', $rol);

        $this->assertSame('rol', $this->eventosDeAuditoria()[0]['entidad']);
    }

    public function test_si_redis_esta_caido_los_helpers_no_interrumpen_la_operacion_y_dejan_respaldo(): void
    {
        $usuario = $this->usuario();
        $anteriores = $this->emisor()->instantanea($usuario);
        $this->redis->caido = true;

        $usuario->update(['nombre' => 'Otra']);
        $this->emisor()->registrarModificacion('usuarios', 'Usuario modificado', $usuario, $anteriores);

        $this->assertCount(1, app(RespaldoAuditoriaService::class)->entradas());
    }
}
