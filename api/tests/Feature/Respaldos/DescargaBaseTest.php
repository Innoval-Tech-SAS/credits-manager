<?php

namespace Tests\Feature\Respaldos;

use App\Geografia\Models\Pais;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\AfirmaAuditoria;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class DescargaBaseTest extends TestCase
{
    use ActsAsAdmin;
    use AfirmaAuditoria;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    /** Contenido SQL del archivo que devolvió la última respuesta. */
    private function sqlDeLaRespuesta($respuesta): string
    {
        $ruta = $respuesta->baseResponse->getFile()->getPathname();
        $sql = gzdecode(file_get_contents($ruta));

        $this->assertNotFalse($sql, 'El archivo descargado debe ser un gzip válido.');

        return $sql;
    }

    /** Un cliente identificable por nombre en cada empresa. */
    private function crearClienteEn(string $nombre): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->actingAsAdmin($empresa);
        $this->crearCliente(['nombre' => $nombre]);
        $this->salirDeSesion();
    }

    public function test_el_superadmin_descarga_la_base_completa_con_todas_las_empresas(): void
    {
        $this->crearClienteEn('Cliente Alfa Único');
        $this->crearClienteEn('Cliente Beta Único');
        $this->actingAsSuperAdmin();

        $respuesta = $this->get('/api/v1/respaldos/base')->assertOk();
        $respuesta->assertHeader('Content-Disposition');
        $respuesta->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringContainsString('base-completa-', $respuesta->headers->get('Content-Disposition'));

        $sql = $this->sqlDeLaRespuesta($respuesta);
        $this->assertStringContainsString('Cliente Alfa Único', $sql);
        $this->assertStringContainsString('Cliente Beta Único', $sql);
        $this->assertStringContainsString('SET FOREIGN_KEY_CHECKS=0;', $sql);
        $this->assertStringContainsString('SET FOREIGN_KEY_CHECKS=1;', $sql);
    }

    public function test_el_archivo_nunca_lleva_hashes_de_contrasena(): void
    {
        $this->actingAsSuperAdmin();

        $sql = $this->sqlDeLaRespuesta($this->get('/api/v1/respaldos/base')->assertOk());

        $this->assertStringNotContainsString('irrelevante-en-tests', $sql, 'Ningún hash de contraseña sale en la descarga.');
        $this->assertStringContainsString('`password_hash`', $sql, 'La columna se conserva, solo sin valor.');
    }

    public function test_el_admin_descarga_solo_los_datos_de_su_empresa(): void
    {
        $empresaB = $this->crearEmpresaDePrueba(['nombre_empresa' => 'Empresa Beta Única']);
        $this->actingAsAdmin($empresaB);
        $this->crearCliente(['nombre' => 'Cliente Beta Único']);
        $this->salirDeSesion();

        $empresaA = $this->crearEmpresaDePrueba(['nombre_empresa' => 'Empresa Alfa Única']);
        $this->actingAsAdmin($empresaA);
        $this->crearCliente(['nombre' => 'Cliente Alfa Único']);

        $respuesta = $this->get('/api/v1/respaldos/empresa')->assertOk();

        $sql = $this->sqlDeLaRespuesta($respuesta);
        $this->assertStringContainsString('Cliente Alfa Único', $sql);
        $this->assertStringContainsString('Empresa Alfa Única', $sql, 'La fila de la propia empresa sí sale.');
        $this->assertStringNotContainsString('Cliente Beta Único', $sql, 'Los datos de otra empresa nunca salen en la descarga.');
        $this->assertStringNotContainsString('Empresa Beta Única', $sql, 'La fila de otra empresa nunca sale.');
        $this->assertStringNotContainsString('irrelevante-en-tests', $sql);
        $this->assertStringNotContainsString('DROP TABLE', $sql, 'La descarga de empresa no borra lo existente al restaurarla.');
    }

    public function test_los_catalogos_sin_empresa_solo_entran_si_estan_en_la_lista(): void
    {
        $this->municipioDePrueba();
        $this->assertGreaterThan(0, Pais::count());

        $empresa = $this->crearEmpresaDePrueba();
        $this->actingAsAdmin($empresa);

        config(['respaldos.tablas_globales' => []]);
        $sinLista = $this->sqlDeLaRespuesta($this->get('/api/v1/respaldos/empresa')->assertOk());
        $this->assertStringNotContainsString('INSERT INTO `paises`', $sinLista);
        $this->assertStringContainsString('paises', $sinLista, 'La omisión queda registrada al pie del archivo.');

        config(['respaldos.tablas_globales' => ['paises']]);
        $conLista = $this->sqlDeLaRespuesta($this->get('/api/v1/respaldos/empresa')->assertOk());
        $this->assertStringContainsString('INSERT INTO `paises`', $conLista);
    }

    public function test_solo_el_superadmin_descarga_la_base_completa(): void
    {
        $this->actingAsAdmin($this->crearEmpresaDePrueba());
        $this->get('/api/v1/respaldos/base')->assertForbidden();

        $this->actingAsAsesor($this->crearEmpresaDePrueba());
        $this->get('/api/v1/respaldos/base')->assertForbidden();
    }

    public function test_el_superadmin_no_descarga_como_si_fuera_de_una_empresa(): void
    {
        $this->actingAsSuperAdmin();
        $this->get('/api/v1/respaldos/empresa')->assertForbidden();
    }

    public function test_un_asesor_no_descarga_los_datos_de_la_empresa(): void
    {
        $this->actingAsAsesor($this->crearEmpresaDePrueba());
        $this->get('/api/v1/respaldos/empresa')->assertForbidden();
    }

    public function test_sin_sesion_no_hay_descarga(): void
    {
        $this->getJson('/api/v1/respaldos/base')->assertUnauthorized();
        $this->getJson('/api/v1/respaldos/empresa')->assertUnauthorized();
    }

    public function test_cada_descarga_queda_en_la_bitacora(): void
    {
        $this->actingAsSuperAdmin();
        $this->get('/api/v1/respaldos/base')->assertOk();

        $eventos = $this->eventosDeAuditoria('exportar', 'respaldos');
        $this->assertCount(1, $eventos);
        $this->assertNull($eventos[0]['empresa_id']);

        $empresa = $this->crearEmpresaDePrueba();
        $this->actingAsAdmin($empresa);
        $this->get('/api/v1/respaldos/empresa')->assertOk();

        $eventos = $this->eventosDeAuditoria('exportar', 'respaldos');
        $this->assertCount(2, $eventos);
        $this->assertSame($empresa->id, $eventos[1]['empresa_id']);
    }
}
