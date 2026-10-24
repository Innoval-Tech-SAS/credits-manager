<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /** Mismo criterio que el panel de salud del superadmin (`EsSuperAdmin`, canal `monitoreo`). */
    protected function authorization(): void
    {
        Horizon::auth(fn (Request $peticion) => (bool) $peticion->user()?->es_superadmin);
    }
}
