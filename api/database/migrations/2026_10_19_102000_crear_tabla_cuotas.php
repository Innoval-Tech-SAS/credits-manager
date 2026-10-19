<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sin `empresa_id` propio: se scope vía `credito_id` (ver `EmpresaScope` en el Model). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credito_id')->constrained('creditos')->cascadeOnDelete();
            $table->integer('numero_cuota');
            $table->date('fecha_vencimiento');
            $table->bigInteger('valor');
            $table->bigInteger('valor_interes')->default(0);
            $table->timestamp('interes_devengado_en')->nullable();
            $table->bigInteger('valor_otros_gastos')->default(0);
            $table->bigInteger('valor_capital')->default(0);
            $table->bigInteger('saldo_capital')->default(0);
            $table->bigInteger('valor_pagado')->default(0);
            $table->string('estado', 20)->default('pendiente');
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->index(['estado', 'fecha_vencimiento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuotas');
    }
};
