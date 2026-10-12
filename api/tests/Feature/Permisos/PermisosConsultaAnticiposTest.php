<?php

namespace Tests\Feature\Permisos;

use App\Empresas\Models\Empresa;
use App\Terceros\Models\Tercero;
use App\Usuarios\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\TestCase;

/**
 * Los anticipos se consultan con `anticipos.listar` (o el lado que cruzan, o `anticipos.crear`), y `anticipos.crear`
 * ya no abre las cuentas por pagar y por cobrar completas: solo los documentos del tercero que va a cruzar.
 */
class PermisosConsultaAnticiposTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesAuditoriaFixtures;
    use CreatesContabilidadFixtures;
    use RefreshDatabase;

    private const CARTERA = '/api/v1/cartera-terceros';

    private Empresa $empresa;

    private Tercero $tercero;

    private function prepararEmpresa(): void
    {
        $admin = $this->actingAsAdmin();
        $this->empresa = Empresa::findOrFail($admin->empresa_id);
        $this->tercero = $this->crearTercero();
    }

    private function actuarCon(array $permisos): Usuario
    {
        return $this->actingAsRolConPermisos('rol_'.uniqid(), $permisos, $this->empresa);
    }

    public function test_anticipos_listar_consulta_los_anticipos_de_ambos_lados_y_nada_mas(): void
    {
        $this->prepararEmpresa();
        $this->actuarCon(['anticipos.listar']);

        $this->getJson(self::CARTERA.'?naturaleza=anticipo_proveedor')->assertOk();
        $this->getJson(self::CARTERA.'?naturaleza=anticipo_cliente')->assertOk();
        $this->getJson(self::CARTERA.'?naturaleza=por_pagar')->assertForbidden();
        $this->getJson(self::CARTERA."?naturaleza=por_cobrar&tercero_id={$this->tercero->id}")->assertForbidden();
        $this->postJson('/api/v1/anticipos/cruzar', [])->assertForbidden();
    }

    public function test_anticipos_crear_no_abre_la_cartera_completa_solo_la_del_tercero_a_cruzar(): void
    {
        $this->prepararEmpresa();
        $this->actuarCon(['anticipos.crear']);

        $this->getJson(self::CARTERA.'?naturaleza=por_pagar')->assertForbidden();
        $this->getJson(self::CARTERA.'?naturaleza=por_cobrar')->assertForbidden();
        $this->getJson(self::CARTERA.'/edades?naturaleza=por_pagar')->assertForbidden();

        $this->getJson(self::CARTERA."?naturaleza=por_pagar&tercero_id={$this->tercero->id}&estado=pendiente")->assertOk();
        $this->getJson(self::CARTERA."?naturaleza=anticipo_proveedor&tercero_id={$this->tercero->id}&estado=pendiente")->assertOk();
        $this->getJson("/api/v1/terceros/{$this->tercero->id}/documentos-pendientes?naturaleza=por_cobrar")->assertOk();
    }

    public function test_cada_lado_de_la_cartera_solo_lee_los_anticipos_de_su_lado(): void
    {
        $this->prepararEmpresa();
        $this->actuarCon(['contabilidad.listar_cuentas_por_pagar']);

        $this->getJson(self::CARTERA.'?naturaleza=por_pagar')->assertOk();
        $this->getJson(self::CARTERA.'?naturaleza=anticipo_proveedor')->assertOk();
        $this->getJson(self::CARTERA.'?naturaleza=anticipo_cliente')->assertForbidden();
        $this->getJson(self::CARTERA.'?naturaleza=por_cobrar')->assertForbidden();
    }

    public function test_un_tercero_de_otra_empresa_no_abre_la_cartera_con_anticipos_crear(): void
    {
        $this->prepararEmpresa();
        $ajeno = $this->tercero;

        $this->actingAsAdmin();
        $this->empresa = Empresa::findOrFail(auth()->user()->empresa_id);
        $this->actuarCon(['anticipos.crear']);

        $this->getJson(self::CARTERA."?naturaleza=por_pagar&tercero_id={$ajeno->id}")->assertUnprocessable()->assertJsonValidationErrors('tercero_id');
        $this->getJson("/api/v1/terceros/{$ajeno->id}/documentos-pendientes?naturaleza=por_pagar")->assertNotFound();
    }
}
