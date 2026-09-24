<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `municipios` deja de ser un catálogo por empresa y pasa a ser geografía global (país → estado → municipio).
 * Lo contable por empresa vive en `tarifas_reteica_municipio`, que solo apunta al municipio.
 */
return new class extends Migration
{
    public function up(): void
    {
        $conMunicipio = DB::table('gastos')->whereNotNull('municipio_id')->exists()
            || DB::table('tarifas_reteica_municipio')->exists();

        if ($conMunicipio) {
            throw new RuntimeException('Hay gastos o tarifas ReteICA ligados a los municipios por empresa: cruzarlos con el catálogo global antes de migrar.');
        }

        $this->soltarClavesAMunicipios();

        Schema::dropIfExists('municipios');

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

        $this->ponerClavesAMunicipios();
    }

    public function down(): void
    {
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
