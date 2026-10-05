<?php

namespace Tests\Feature\Metas;

use App\Empresas\Models\Empresa;
use App\Metas\Enums\TipoMeta;
use App\Metas\Services\EvaluacionMetasService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\Concerns\CreatesMetasFixtures;
use Tests\TestCase;

class AislamientoMetasTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use CreatesMetasFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Alerta escrita directo: la tabla no tiene API de creación pública, el aislamiento se prueba sobre la lectura. */
    private function insertarAlerta(int $empresaId, int $metaId, ?int $usuarioId, string $estado = 'en_riesgo'): int
    {
        return DB::table('alertas_metas')->insertGetId([
            'empresa_id' => $empresaId,
            'meta_id' => $metaId,
            'usuario_id' => $usuarioId,
            'periodo' => '2026-10',
            'estado' => $estado,
            'generada_en' => '2026-10-12 10:00:00',
        ]);
    }

    public function test_otra_empresa_no_ve_ni_modifica_las_metas_ni_las_alertas_ajenas(): void
    {
        $adminA = $this->actingAsAdmin();
        $meta = $this->crearMeta('2026-10', TipoMeta::Colocacion, 1000000);
        $alertaId = $this->insertarAlerta($adminA->empresa_id, $meta->id, null);

        $this->actingAsAdmin();

        $this->getJson('/api/v1/metas?periodo=2026-10')->assertOk()->assertJsonCount(0, 'metas');
        $this->putJson("/api/v1/metas/{$meta->id}", ['valor_objetivo' => 1])->assertNotFound();
        $this->putJson("/api/v1/metas/{$meta->id}/asesores", ['asesores' => []])->assertNotFound();
        $this->postJson("/api/v1/metas/alertas/{$alertaId}/vista")->assertNotFound();
        $this->getJson('/api/v1/metas/alertas')->assertOk()->assertJsonCount(0, 'alertas');
    }

    public function test_el_job_de_una_empresa_no_cierra_metas_de_otra(): void
    {
        $adminA = $this->actingAsAdmin();
        $empresaA = $adminA->empresa_id;
        $this->crearMeta('2026-09', TipoMeta::Colocacion, 1000000);

        $adminB = $this->actingAsAdmin();
        $empresaB = $adminB->empresa_id;
        $this->crearMeta('2026-09', TipoMeta::Colocacion, 1000000);

        $this->salirDeSesion();

        $ahora = CarbonImmutable::parse('2026-10-01 10:00:00', 'America/Bogota');
        app(EvaluacionMetasService::class)->evaluarEmpresa((int) $empresaB, $ahora);

        $this->assertSame(0, DB::table('metas_cierres')->where('empresa_id', $empresaA)->count());
        $this->assertSame(1, DB::table('metas_cierres')->where('empresa_id', $empresaB)->whereNull('asesor_id')->count());
    }

    public function test_un_asesor_no_ve_ni_marca_las_alertas_de_otro_asesor(): void
    {
        $admin = $this->actingAsAdmin();
        $empresa = Empresa::findOrFail($admin->empresa_id);
        $ana = $this->crearAsesor($empresa, 'Ana');
        $beto = $this->crearAsesor($empresa, 'Beto');
        $meta = $this->crearMeta('2026-10', TipoMeta::Colocacion, 1000000);
        $this->distribuirMeta($meta, $ana, 600000);
        $this->configurarMetas('meta_consolidada_visible_asesor', true);
        $alertaDeAna = $this->insertarAlerta($empresa->id, $meta->id, $ana->id);

        $this->actingAs($beto, 'sanctum');

        $this->getJson('/api/v1/metas/alertas')->assertOk()->assertJsonCount(0, 'alertas');
        $this->postJson("/api/v1/metas/alertas/{$alertaDeAna}/vista")->assertNotFound();

        $this->actingAs($ana, 'sanctum');

        $this->getJson('/api/v1/metas/alertas')->assertOk()->assertJsonCount(1, 'alertas');
        $this->postJson("/api/v1/metas/alertas/{$alertaDeAna}/vista")->assertOk();
        $this->assertNotNull(DB::table('alertas_metas')->where('id', $alertaDeAna)->value('vista_en'));
    }
}
