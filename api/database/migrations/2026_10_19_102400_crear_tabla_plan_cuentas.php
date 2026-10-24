<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_cuentas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('codigo', 20);
            $table->string('nombre', 150);
            $table->string('naturaleza', 20);
            $table->unsignedInteger('nivel')->default(1);
            $table->foreignId('cuenta_padre_id')->nullable()->constrained('plan_cuentas')->nullOnDelete();
            $table->string('tipo', 20);
            $table->boolean('permite_movimiento')->default(true);
            $table->boolean('exige_tercero')->default(false);
            $table->boolean('exige_documento_cruce')->default(false);
            $table->boolean('exige_vencimiento')->default(false);
            $table->boolean('exige_centro_costo')->default(false);
            $table->boolean('activa')->default(true);
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique(['empresa_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_cuentas');
    }
};
