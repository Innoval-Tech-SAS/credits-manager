<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuraciones_correo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('remitente', 190);
            $table->string('nombre_remitente', 120);
            $table->string('driver', 20);
            $table->string('host', 190)->nullable();
            $table->unsignedSmallInteger('puerto')->nullable();
            $table->string('cifrado', 10);
            $table->string('usuario', 190)->nullable();
            // Cifrada con APP_KEY (cast `encrypted`). Nunca sale por la API.
            $table->text('clave')->nullable();
            // Plantilla editable por la empresa. El cuerpo es HTML; las variables van entre llaves ({cliente}).
            $table->string('plantilla_asunto', 200);
            $table->longText('plantilla_cuerpo');
            $table->foreignId('actualizado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            // Una sola configuración de correo por empresa.
            $table->unique('empresa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones_correo');
    }
};
