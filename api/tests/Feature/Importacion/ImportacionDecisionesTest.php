<?php

namespace Tests\Feature\Importacion;

use App\Empresas\Models\Empresa;
use App\Importacion\Enums\EstadoImportacion;
use App\Usuarios\Models\Permiso;
use App\Usuarios\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\ImportaArchivos;
use Tests\TestCase;

class ImportacionDecisionesTest extends TestCase
{
    use ActsAsAdmin;
    use ImportaArchivos;
    use RefreshDatabase;

    private const ENCABEZADO = "Cédula;Nombre completo;Teléfono;Correo electrónico;Empresa (nómina);Dirección;Barrio;Ciudad;Fecha de vínculo (si se conoce);Observaciones\n";

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararImportaciones();
    }

    /** Permiso del rol del usuario: así una excepción de revocación tiene algo que quitar. */
    private function otorgarPorRol(Usuario $usuario, string $modulo, string $accion): void
    {
        $usuario->rol->permisos()->attach(Permiso::query()->where(['modulo' => $modulo, 'accion' => $accion])->value('id'));
    }

    /** Excepción por usuario: concede (true) o revoca (false) un permiso aunque el rol lo tenga o no. */
    private function excepcion(Usuario $usuario, string $modulo, string $accion, bool $concedido): void
    {
        $usuario->permisosExtra()->attach(Permiso::query()->where(['modulo' => $modulo, 'accion' => $accion])->value('id'), ['concedido' => $concedido]);
    }

    /** Asesor de la misma empresa que el admin: sus permisos salen de su rol más las excepciones. */
    private function asesorDeLaEmpresa(Usuario $admin): Usuario
    {
        $asesor = $this->actingAsAsesor(Empresa::query()->findOrFail($admin->empresa_id));
        $this->excepcion($asesor, 'importaciones', 'listar', true);
        $this->excepcion($asesor, 'importaciones', 'ejecutar', true);

        return $asesor;
    }

    public function test_decidir_sin_ningun_campo_responde_422(): void
    {
        $this->actingAsAdmin();
        $id = $this->subirArchivo('clientes', self::ENCABEZADO."1001;Ana Pérez;;;Empresa Nueva;;;;;\n");
        $this->ejecutarValidacion($id);

        $this->postJson("/api/v1/importaciones/{$id}/decisiones", [])->assertUnprocessable()->assertJsonValidationErrors('crear_empleadores');
    }

    public function test_generar_asiento_en_una_hoja_sin_regla_contable_responde_422(): void
    {
        $this->actingAsAdmin();
        $id = $this->subirArchivo('clientes', self::ENCABEZADO."1001;Ana Pérez;;;;;;;;\n");
        $this->ejecutarValidacion($id);

        $this->postJson("/api/v1/importaciones/{$id}/decisiones", ['generar_asiento' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('generar_asiento');
    }

    public function test_confirmar_dos_veces_no_escribe_dos_veces(): void
    {
        $this->actingAsAdmin();
        $id = $this->subirArchivo('clientes', self::ENCABEZADO."1001;Ana Pérez;;;;;;;;\n");
        $this->ejecutarValidacion($id);

        $this->postJson("/api/v1/importaciones/{$id}/confirmar")->assertStatus(202);
        $this->postJson("/api/v1/importaciones/{$id}/confirmar")->assertUnprocessable()->assertJsonValidationErrors('estado');
    }

    public function test_no_se_puede_cancelar_una_importacion_que_ya_esta_escribiendo(): void
    {
        $this->actingAsAdmin();
        $id = $this->subirArchivo('clientes', self::ENCABEZADO."1001;Ana Pérez;;;;;;;;\n");
        $this->ejecutarValidacion($id);
        $this->postJson("/api/v1/importaciones/{$id}/confirmar")->assertStatus(202);

        $this->postJson("/api/v1/importaciones/{$id}/cancelar")->assertUnprocessable()->assertJsonValidationErrors('estado');
        $this->assertSame(EstadoImportacion::Importando, $this->importacion($id)->estado);
    }

    public function test_pedir_crear_empleadores_sin_clientes_crear_responde_403(): void
    {
        $asesor = $this->actingAsAsesor();
        $this->excepcion($asesor, 'importaciones', 'ejecutar', true);
        $this->otorgarPorRol($asesor, 'clientes', 'crear');
        $this->excepcion($asesor, 'clientes', 'crear', false);

        $this->postJson('/api/v1/importaciones', [
            'tipo' => 'clientes',
            'crear_empleadores' => '1',
            'archivo' => UploadedFile::fake()->createWithContent('c.csv', self::ENCABEZADO."1001;Ana Pérez;;;;;;;;\n"),
        ])->assertForbidden();

        $this->assertDatabaseCount('importaciones', 0);
    }

    public function test_pedir_el_asiento_sin_contabilidad_crear_manual_responde_403(): void
    {
        $asesor = $this->actingAsAsesor();
        $this->excepcion($asesor, 'importaciones', 'ejecutar', true);
        $this->excepcion($asesor, 'pagos', 'crear', true);

        $this->postJson('/api/v1/importaciones', [
            'tipo' => 'pagos',
            'generar_asiento' => '1',
            'archivo' => UploadedFile::fake()->createWithContent('p.csv', "Código de crédito;Fecha de pago;Valor pagado;Tipo (Cuota completa/Abono parcial)\n"),
        ])->assertForbidden();

        $this->assertDatabaseCount('importaciones', 0);
    }

    public function test_la_decision_de_crear_empleadores_responde_403_si_el_usuario_perdio_clientes_crear(): void
    {
        $admin = $this->actingAsAdmin();
        $id = $this->subirArchivo('clientes', self::ENCABEZADO."1001;Ana Pérez;;;Empresa Nueva;;;;;\n");
        $this->ejecutarValidacion($id);

        $asesor = $this->asesorDeLaEmpresa($admin);
        $this->otorgarPorRol($asesor, 'clientes', 'crear');
        $this->excepcion($asesor, 'clientes', 'crear', false);

        $this->postJson("/api/v1/importaciones/{$id}/decisiones", ['crear_empleadores' => true])->assertForbidden();
        $this->assertDatabaseCount('empleadores', 0);
        $this->assertSame(EstadoImportacion::PendienteDecision, $this->importacion($id)->estado);
    }

    public function test_el_asesor_con_permisos_de_importacion_puede_decidir_si_no_se_le_revoca_nada(): void
    {
        $admin = $this->actingAsAdmin();
        $id = $this->subirArchivo('clientes', self::ENCABEZADO."1001;Ana Pérez;;;Empresa Nueva;;;;;\n");
        $this->ejecutarValidacion($id);

        $this->asesorDeLaEmpresa($admin);

        $this->postJson("/api/v1/importaciones/{$id}/decisiones", ['crear_empleadores' => false])->assertOk();
        $this->assertSame(false, $this->importacion($id)->decision_crear_empleadores);
    }
}
