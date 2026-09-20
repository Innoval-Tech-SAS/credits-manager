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
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 150);
            $table->string('naturaleza');
            $table->unsignedInteger('nivel')->default(1);
            $table->foreignId('cuenta_padre_id')->nullable()->constrained('plan_cuentas')->nullOnDelete();
            $table->string('tipo');
            $table->boolean('permite_movimiento')->default(true);
            $table->boolean('activa')->default(true);
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_cuentas');
    }
};
