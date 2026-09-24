<?php

use Database\Seeders\GeografiaSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `municipios` deja de ser un catálogo por empresa y pasa a ser geografía global (país → estado → municipio).
 * Lo contable por empresa vive en `tarifas_reteica_municipio`, que solo apunta al municipio.
 * Si ya había municipios por empresa, se cruzan por código DANE con el catálogo y sus referencias se reasignan.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->soltarClavesAMunicipios();
        Schema::rename('municipios', 'municipios_por_empresa');

        Schema::create('municipios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estado_id')->constrained('estados');
            $table->string('codigo', 10);
            $table->string('nombre', 150);
            $table->boolean('es_capital')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique(['estado_id', 'codigo']);
            $table->index(['estado_id', 'nombre']);
        });

        $this->reasignarReferencias();

        Schema::drop('municipios_por_empresa');
        $this->ponerClavesAMunicipios();
    }

    public function down(): void
    {
        if (DB::table('gastos')->whereNotNull('municipio_id')->exists() || DB::table('tarifas_reteica_municipio')->exists()) {
            throw new RuntimeException('No se puede revertir: hay gastos o tarifas ReteICA ligados al catálogo global de municipios.');
        }

        $this->soltarClavesAMunicipios();

        Schema::dropIfExists('municipios');

        Schema::create('municipios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('codigo', 20);
            $table->string('nombre', 150);
            $table->boolean('activo')->default(true);
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique(['empresa_id', 'codigo']);
        });

        $this->ponerClavesAMunicipios();
    }

    private function reasignarReferencias(): void
    {
        $antiguos = DB::table('municipios_por_empresa')->get(['id', 'codigo', 'nombre']);

        if ($antiguos->isEmpty()) {
            return;
        }

        Artisan::call('db:seed', ['--class' => GeografiaSeeder::class, '--force' => true]);

        $nuevoIdPorAntiguo = [];

        foreach ($antiguos as $antiguo) {
            $nuevoId = DB::table('municipios')->where('codigo', $antiguo->codigo)->value('id');

            if ($nuevoId === null) {
                throw new RuntimeException("El municipio {$antiguo->codigo} {$antiguo->nombre} no está en el catálogo DIVIPOLA: agrégalo con su código DANE antes de migrar.");
            }

            $nuevoIdPorAntiguo[$antiguo->id] = $nuevoId;
        }

        // Un solo UPDATE por tabla: reasignar de a uno podría pisar un id nuevo que coincide con otro antiguo.
        $casos = implode(' ', array_fill(0, count($nuevoIdPorAntiguo), 'WHEN ? THEN ?'));
        $enlaces = collect($nuevoIdPorAntiguo)->flatMap(fn (int $nuevo, int $antiguo) => [$antiguo, $nuevo])->all();
        $antiguosIds = array_keys($nuevoIdPorAntiguo);
        $marcas = implode(',', array_fill(0, count($antiguosIds), '?'));

        foreach (['gastos', 'tarifas_reteica_municipio'] as $tabla) {
            DB::update(
                "UPDATE {$tabla} SET municipio_id = CASE municipio_id {$casos} END WHERE municipio_id IN ({$marcas})",
                [...$enlaces, ...$antiguosIds],
            );
        }
    }

    private function soltarClavesAMunicipios(): void
    {
        Schema::table('gastos', fn (Blueprint $table) => $table->dropForeign(['municipio_id']));
        Schema::table('tarifas_reteica_municipio', fn (Blueprint $table) => $table->dropForeign(['municipio_id']));
    }

    private function ponerClavesAMunicipios(): void
    {
        Schema::table('gastos', fn (Blueprint $table) => $table->foreign('municipio_id')->references('id')->on('municipios'));
        Schema::table('tarifas_reteica_municipio', fn (Blueprint $table) => $table->foreign('municipio_id')->references('id')->on('municipios'));
    }
};
