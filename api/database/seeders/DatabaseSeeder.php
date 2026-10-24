<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PermisoSeeder::class);
        $this->call(GeografiaSeeder::class);
        $this->call(UsuarioSeeder::class);
        $this->call(TareaProgramadaSeeder::class);
        $this->call(ConfiguracionRespaldoExternoSeeder::class);
    }
}
