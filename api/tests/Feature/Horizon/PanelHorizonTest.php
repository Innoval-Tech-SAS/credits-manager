<?php

namespace Tests\Feature\Horizon;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

/** El panel lo registra el paquete Horizon; aquí solo se prueba el cierre de `Horizon::auth` en HorizonServiceProvider. */
class PanelHorizonTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    public function test_invitado_no_entra_al_panel_de_horizon(): void
    {
        $this->get('/horizon')->assertForbidden();
    }

    public function test_admin_sin_superadmin_no_entra_al_panel_de_horizon(): void
    {
        $this->actingAsAdmin();

        $this->get('/horizon')->assertForbidden();
    }

    public function test_superadmin_entra_al_panel_de_horizon(): void
    {
        $this->actingAsSuperAdmin();

        $this->get('/horizon')->assertOk();
    }
}
