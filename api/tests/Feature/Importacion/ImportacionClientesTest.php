<?php

namespace Tests\Feature\Importacion;

use App\Clientes\Models\Cliente;
use App\Empleadores\Models\Empleador;
use App\Importacion\Enums\EstadoImportacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\Concerns\ImportaArchivos;
use Tests\TestCase;

class ImportacionClientesTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use ImportaArchivos;
    use RefreshDatabase;

    private const ENCABEZADO = "Cédula;Nombre completo;Teléfono;Correo electrónico;Empresa (nómina);Dirección;Barrio;Ciudad;Fecha de vínculo (si se conoce);Observaciones\n";

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararImportaciones();
    }

    public function test_validar_no_escribe_y_confirmar_escribe_lo_aceptado(): void
    {
        $this->actingAsAdmin();
        $this->crearEmpleador(['nombre' => 'Secretaría de Movilidad']);

        $id = $this->subirArchivo('clientes', self::ENCABEZADO
            ."1001;Ana Pérez Gómez;3001234567;ana@example.com;Secretaría de Movilidad;Calle 1;Centro;Santa Marta;15/03/2021;\n"
            ."1002;Luis Ramírez;;;;;;;;\n"
            ."1003;Carlos;;;;;;;;\n");

        $this->ejecutarValidacion($id);

        $this->assertDatabaseCount('clientes', 0);
        $importacion = $this->importacion($id);
        $this->assertSame(EstadoImportacion::PendienteDecision, $importacion->estado);
        $this->assertSame(2, $importacion->filas_aceptadas);
        $this->assertSame(1, $importacion->filas_rechazadas);
        $this->assertSame(0, $importacion->empleadores_pendientes);

        $this->postJson("/api/v1/importaciones/{$id}/confirmar")->assertStatus(202);
        $this->ejecutarEscritura($id);

        $this->assertDatabaseCount('clientes', 2);
        $this->assertDatabaseHas('terceros', ['numero_documento' => '1001', 'nombres' => 'Ana', 'apellidos' => 'Pérez Gómez', 'correo' => 'ana@example.com']);
        $this->assertDatabaseHas('clientes', ['empleador_id' => Empleador::query()->where('nombre', 'Secretaría de Movilidad')->value('id')]);
        $this->assertSame(EstadoImportacion::Completada, $this->importacion($id)->estado);
    }

    public function test_resubir_el_mismo_archivo_reutiliza_los_clientes_y_no_duplica(): void
    {
        $this->actingAsAdmin();
        $csv = self::ENCABEZADO."1001;Ana Pérez;;;;;;;;\n";

        $this->importarCompleto('clientes', $csv);
        $segunda = $this->importarCompleto('clientes', $csv);

        $this->assertDatabaseCount('clientes', 1);
        $this->assertDatabaseCount('terceros', 1);
        $this->assertSame(1, $this->importacion($segunda)->filas_aceptadas);
    }

    public function test_empleador_faltante_pide_decision_y_rechazar_deja_sus_clientes_rechazados(): void
    {
        $this->actingAsAdmin();
        $id = $this->subirArchivo('clientes', self::ENCABEZADO."1001;Ana Pérez;;;Empresa Inexistente SAS;;;;;\n");

        $this->ejecutarValidacion($id);

        $this->assertSame(1, $this->importacion($id)->empleadores_pendientes);
        $this->assertSame(1, $this->importacion($id)->filas_pendientes);

        $this->postJson("/api/v1/importaciones/{$id}/confirmar")
            ->assertUnprocessable()->assertJsonValidationErrors('crear_empleadores');

        $this->postJson("/api/v1/importaciones/{$id}/decisiones", ['crear_empleadores' => false])->assertOk();
        $this->postJson("/api/v1/importaciones/{$id}/confirmar")->assertStatus(202);
        $this->ejecutarEscritura($id);

        $this->assertDatabaseCount('clientes', 0);
        $this->assertDatabaseCount('empleadores', 0);
        $this->assertSame(1, $this->importacion($id)->filas_rechazadas);
        $this->assertStringContainsString('no creado', implode(' ', array_column($this->erroresDe($id), 'motivo')));
    }

    public function test_aceptar_crea_el_empleador_una_sola_vez_y_enlaza_al_cliente(): void
    {
        $this->actingAsAdmin();
        $csv = self::ENCABEZADO
            ."1001;Ana Pérez;;;Empresa Nueva SAS;;;;;\n"
            ."1002;Luis Ramírez;;;empresa nueva sas;;;;;\n";

        $id = $this->subirArchivo('clientes', $csv);
        $this->ejecutarValidacion($id);
        $this->postJson("/api/v1/importaciones/{$id}/decisiones", ['crear_empleadores' => true])->assertOk();
        $this->postJson("/api/v1/importaciones/{$id}/confirmar")->assertStatus(202);
        $this->ejecutarEscritura($id);

        $this->assertDatabaseCount('empleadores', 1);
        $this->assertDatabaseCount('clientes', 2);
        $empleadorId = Empleador::query()->value('id');
        $this->assertSame(2, Cliente::query()->where('empleador_id', $empleadorId)->count());
    }

    public function test_sin_empleadores_faltantes_no_pide_decision(): void
    {
        $this->actingAsAdmin();
        $this->crearEmpleador(['nombre' => 'Empresa Conocida']);
        $id = $this->subirArchivo('clientes', self::ENCABEZADO."1001;Ana Pérez;;;Empresa Conocida;;;;;\n");
        $this->ejecutarValidacion($id);

        $this->postJson("/api/v1/importaciones/{$id}/decisiones", ['crear_empleadores' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('crear_empleadores');

        $this->postJson("/api/v1/importaciones/{$id}/confirmar")->assertStatus(202);
    }

    public function test_la_fila_de_ejemplo_de_la_plantilla_se_rechaza_con_un_mensaje_claro(): void
    {
        $this->actingAsAdmin();

        $id = $this->importarCompleto('clientes', self::ENCABEZADO
            ."53125435;Adriana Maria Daza Mesa;;;Secretaria de Movilidad;;;;45213;\n"
            ."1001;Ana Pérez;;;;;;;;\n");

        $this->assertSame(1, $this->importacion($id)->filas_aceptadas);
        $this->assertStringContainsString('ejemplo de la plantilla', implode(' ', $this->motivosDeRechazo($id)));
        $this->assertDatabaseMissing('terceros', ['numero_documento' => '53125435']);
    }

    public function test_un_nombre_sin_apellidos_se_rechaza(): void
    {
        $this->actingAsAdmin();

        $id = $this->importarCompleto('clientes', self::ENCABEZADO."1003;Carlos;;;;;;;;\n");

        $this->assertSame(0, $this->importacion($id)->filas_aceptadas);
        $this->assertStringContainsString('nombres y apellidos', implode(' ', $this->motivosDeRechazo($id)));
    }
}
