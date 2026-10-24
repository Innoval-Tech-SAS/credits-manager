<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Configuración SMTP por empresa y tipo (`cobranza`/`facturacion`). `clave` va cifrada con APP_KEY (cast `encrypted` en el Model) y nunca sale por la API. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuraciones_correo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('tipo', 20);
            $table->string('remitente', 190);
            $table->string('nombre_remitente', 120);
            $table->string('host', 190)->nullable();
            $table->unsignedSmallInteger('puerto')->nullable();
            $table->string('cifrado', 10);
            $table->string('usuario', 190)->nullable();
            $table->text('clave')->nullable();
            $table->foreignId('actualizado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique(['empresa_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones_correo');
    }
};
