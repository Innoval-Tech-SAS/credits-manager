<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('envios_correo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('cuota_id')->nullable()->constrained('cuotas');
            $table->foreignId('enviado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('plantilla', 40);
            $table->string('destinatario', 190);
            $table->string('asunto', 200);
            // HTML ya renderizado: queda como constancia de lo que se envió.
            $table->longText('cuerpo');
            $table->string('estado', 20);
            // Mensaje fijo y sin datos de conexión; nunca el detalle crudo del servidor SMTP.
            $table->string('detalle_error', 255)->nullable();
            $table->timestamp('enviado_en')->nullable();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->index(['empresa_id', 'cliente_id']);
            $table->index(['empresa_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envios_correo');
    }
};
