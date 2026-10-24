<?php

namespace Tests\Feature\Facturacion;

use App\Creditos\Enums\EstadoCuota;
use App\Empresas\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\AfirmaAuditoria;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\Concerns\CreatesFacturacionFixtures;
use Tests\TestCase;

/** 8.1: cambiar un valor sugerido de la cuota exige el permiso `facturacion.modificar_valores_sugeridos`. */
class ValorSugeridoPermisoFacturacionTest extends TestCase
{
    use ActsAsAdmin;
    use AfirmaAuditoria;
    use CreatesAuditoriaFixtures;
    use CreatesCreditFixtures;
    use CreatesFacturacionFixtures;
    use RefreshDatabase;

    private const BASE = '/api/v1/facturacion/documentos';

    public function test_cambiar_el_valor_sugerido_sin_el_permiso_nuevo_recibe_403(): void
    {
        $admin = $this->actingAsAdmin();
        $this->crearReglasFacturacion();
        $credito = $this->crearCredito($this->crearCliente());
        $cuota = $this->crearCuota($credito, [
            'numero_cuota' => 1, 'valor_capital' => 250, 'valor_interes' => 0, 'valor_otros_gastos' => 0,
            'estado' => EstadoCuota::Pendiente,
        ]);

        $this->actingAsRolConPermisos('facturador', ['facturacion.crear'], Empresa::find($admin->empresa_id));

        $this->postJson(self::BASE, [
            'cliente_id' => $credito->cliente_id, 'credito_id' => $credito->id,
            'lineas' => [['concepto' => 'Capital cuota 1', 'descripcion' => 'Capital', 'valor' => '300', 'tipo_concepto' => 'capital', 'cuota_id' => $cuota->id]],
        ])->assertStatus(403);

        $this->assertDatabaseMissing('documentos_facturacion', ['credito_id' => $credito->id]);
    }

    public function test_respetar_el_valor_sugerido_no_necesita_el_permiso(): void
    {
        $admin = $this->actingAsAdmin();
        $this->crearReglasFacturacion();
        $credito = $this->crearCredito($this->crearCliente());
        $cuota = $this->crearCuota($credito, [
            'numero_cuota' => 1, 'valor_capital' => 250, 'valor_interes' => 0, 'valor_otros_gastos' => 0,
            'estado' => EstadoCuota::Pendiente,
        ]);

        $this->actingAsRolConPermisos('facturador', ['facturacion.crear'], Empresa::find($admin->empresa_id));

        $this->postJson(self::BASE, [
            'cliente_id' => $credito->cliente_id, 'credito_id' => $credito->id,
            'lineas' => [['concepto' => 'Capital cuota 1', 'descripcion' => 'Capital', 'valor' => '250', 'tipo_concepto' => 'capital', 'cuota_id' => $cuota->id]],
        ])->assertStatus(201);
    }

    public function test_cambiar_el_valor_sugerido_con_el_permiso_se_guarda_y_queda_en_la_bitacora(): void
    {
        $admin = $this->actingAsAdmin();
        $this->crearReglasFacturacion();
        $credito = $this->crearCredito($this->crearCliente());
        $cuota = $this->crearCuota($credito, [
            'numero_cuota' => 1, 'valor_capital' => 250, 'valor_interes' => 0, 'valor_otros_gastos' => 0,
            'estado' => EstadoCuota::Pendiente,
        ]);

        $this->actingAsRolConPermisos(
            'facturador',
            ['facturacion.crear', 'facturacion.modificar_valores_sugeridos'],
            Empresa::find($admin->empresa_id),
        );

        $documento = $this->postJson(self::BASE, [
            'cliente_id' => $credito->cliente_id, 'credito_id' => $credito->id,
            'lineas' => [['concepto' => 'Capital cuota 1', 'descripcion' => 'Capital', 'valor' => '300', 'tipo_concepto' => 'capital', 'cuota_id' => $cuota->id]],
        ])->assertStatus(201)->json('documento');

        $this->assertSame('300.00', $documento['lineas'][0]['valor']);
        $evento = $this->assertEventoAuditoria('modificar', 'facturacion', 'documento_facturacion', null);
        $this->assertSame($credito->id, $evento['valores_nuevos']['credito_id']);
        $this->assertSame('250.00', $evento['valores_nuevos']['overrides'][0]['sugerido']);
        $this->assertSame('300.00', $evento['valores_nuevos']['overrides'][0]['nuevo']);
    }
}
