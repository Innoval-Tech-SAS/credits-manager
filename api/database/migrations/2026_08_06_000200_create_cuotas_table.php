<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credito_id')->constrained('creditos')->cascadeOnDelete();
            $table->integer('numero_cuota');
            $table->date('fecha_vencimiento');
            $table->decimal('valor', 14, 2);
            $table->decimal('valor_interes', 14, 2)->default(0);
            $table->decimal('valor_capital', 14, 2)->default(0);
            $table->decimal('saldo_capital', 14, 2)->default(0);
            $table->decimal('valor_pagado', 14, 2)->default(0);
            $table->string('estado')->default('pendiente');
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuotas');
    }
};
