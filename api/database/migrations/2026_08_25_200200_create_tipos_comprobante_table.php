<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_comprobante', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique();
            $table->string('nombre', 100);
            $table->unsignedInteger('consecutivo_actual')->default(0);
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_comprobante');
    }
};
