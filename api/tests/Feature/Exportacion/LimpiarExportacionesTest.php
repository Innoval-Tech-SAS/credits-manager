<?php

namespace Tests\Feature\Exportacion;

use App\Exportacion\Constants\ExportacionConstants;
use App\Exportacion\Enums\EstadoExportacion;
use App\Exportacion\Enums\FormatoExportacion;
use App\Exportacion\Models\Exportacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class LimpiarExportacionesTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private function exportacion(int $empresaId, int $usuarioId, string $creadaHace, EstadoExportacion $estado = EstadoExportacion::Completada): Exportacion
    {
        $ruta = 'pruebas/'.uniqid().'.csv';
        Storage::disk(ExportacionConstants::DISCO)->put($ruta, 'contenido');

        $exportacion = Exportacion::withoutGlobalScopes()->create([
            'empresa_id' => $empresaId, 'usuario_id' => $usuarioId, 'reporte' => 'auditoria.bitacora',
            'formato' => FormatoExportacion::Csv, 'estado' => $estado, 'ruta_archivo' => $ruta, 'nombre_archivo' => 'x.csv',
        ]);
        $exportacion->forceFill(['creado_en' => now()->sub($creadaHace)])->save();

        return $exportacion;
    }

    public function test_borra_el_archivo_y_archiva_solo_lo_vencido_de_todas_las_empresas(): void
    {
        Storage::fake(ExportacionConstants::DISCO);
        $a = $this->actingAsAdmin();
        $b = $this->actingAsAdmin();

        $vencidaA = $this->exportacion($a->empresa_id, $a->id, '10 days');
        $vencidaB = $this->exportacion($b->empresa_id, $b->id, '8 days', EstadoExportacion::Fallida);
        $reciente = $this->exportacion($a->empresa_id, $a->id, '2 days');
        $enCurso = $this->exportacion($a->empresa_id, $a->id, '30 days', EstadoExportacion::Procesando);

        $this->artisan('exportaciones:limpiar')->expectsOutput('Exportaciones limpiadas: 2')->assertExitCode(0);

        Storage::disk(ExportacionConstants::DISCO)->assertMissing($vencidaA->ruta_archivo);
        Storage::disk(ExportacionConstants::DISCO)->assertMissing($vencidaB->ruta_archivo);
        Storage::disk(ExportacionConstants::DISCO)->assertExists($reciente->ruta_archivo);
        Storage::disk(ExportacionConstants::DISCO)->assertExists($enCurso->ruta_archivo);

        $this->assertSoftDeleted('exportaciones', ['id' => $vencidaA->id], null, 'borrado_en');
        $this->assertSoftDeleted('exportaciones', ['id' => $vencidaB->id], null, 'borrado_en');
        $this->assertDatabaseHas('exportaciones', ['id' => $reciente->id, 'borrado_en' => null]);
        $this->assertDatabaseHas('exportaciones', ['id' => $enCurso->id, 'borrado_en' => null]);
    }

    public function test_respeta_la_antiguedad_indicada(): void
    {
        Storage::fake(ExportacionConstants::DISCO);
        $admin = $this->actingAsAdmin();
        $reciente = $this->exportacion($admin->empresa_id, $admin->id, '2 days');

        $this->artisan('exportaciones:limpiar --dias=1')->expectsOutput('Exportaciones limpiadas: 1')->assertExitCode(0);

        $this->assertSoftDeleted('exportaciones', ['id' => $reciente->id], null, 'borrado_en');
    }
}
