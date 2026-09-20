<?php

namespace Tests\Feature\Auditoria;

use App\Auditoria\Models\BitacoraAuditoria;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\TestCase;

class SoloInsercionTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesAuditoriaFixtures;
    use RefreshDatabase;

    public function test_el_modelo_rechaza_actualizar_un_evento_existente(): void
    {
        $evento = $this->crearEventoBitacora();

        $this->expectException(LogicException::class);

        $evento->update(['descripcion' => 'alterada']);
    }

    public function test_el_modelo_rechaza_eliminar_un_evento(): void
    {
        $evento = $this->crearEventoBitacora();

        try {
            $evento->delete();
            $this->fail('delete() debió lanzar LogicException.');
        } catch (LogicException) {
            $this->assertDatabaseHas('bitacora_auditoria', ['id' => $evento->id]);
        }
    }

    public function test_el_modelo_rechaza_forzar_el_borrado(): void
    {
        $evento = $this->crearEventoBitacora();

        $this->expectException(LogicException::class);

        $evento->forceDelete();
    }

    public function test_la_base_rechaza_un_update_directo(): void
    {
        $evento = $this->crearEventoBitacora(['descripcion' => 'original']);

        try {
            DB::table('bitacora_auditoria')->where('id', $evento->id)->update(['descripcion' => 'alterada']);
            $this->fail('El UPDATE debió ser rechazado por el trigger.');
        } catch (QueryException $excepcion) {
            $this->assertStringContainsString('solo insercion', $excepcion->getMessage());
        }

        $this->assertDatabaseHas('bitacora_auditoria', ['id' => $evento->id, 'descripcion' => 'original']);
    }

    public function test_la_base_rechaza_un_delete_directo(): void
    {
        $evento = $this->crearEventoBitacora();

        try {
            DB::table('bitacora_auditoria')->where('id', $evento->id)->delete();
            $this->fail('El DELETE debió ser rechazado por el trigger.');
        } catch (QueryException $excepcion) {
            $this->assertStringContainsString('solo insercion', $excepcion->getMessage());
        }

        $this->assertDatabaseHas('bitacora_auditoria', ['id' => $evento->id]);
    }

    public function test_la_base_rechaza_un_update_masivo_desde_eloquent(): void
    {
        $this->crearEventoBitacora();

        $this->expectException(QueryException::class);

        BitacoraAuditoria::query()->update(['modulo' => 'alterado']);
    }

    public function test_evento_id_es_unico(): void
    {
        $evento = $this->crearEventoBitacora();

        $this->expectException(QueryException::class);

        $this->crearEventoBitacora(['evento_id' => $evento->evento_id]);
    }
}
