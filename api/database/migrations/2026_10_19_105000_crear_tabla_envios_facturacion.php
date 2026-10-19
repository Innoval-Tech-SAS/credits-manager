<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reenvío por correo del PDF de un documento de facturación. Mismo propósito que `envios_correo` (cobranza),
 * con plantilla opcional y el cuerpo ya renderizado como constancia de lo que se envió. Usa el mismo
 * `ConfiguracionCorreo`/worker/cola `correos` ya existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('envios_facturacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedBigInteger('documento_facturacion_id');
            $table->foreignId('enviado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('destinatario', 190);
            $table->string('asunto', 200);
            $table->foreignId('plantilla_correo_id')->nullable()->constrained('plantillas_correo')->nullOnDelete();
            $table->longText('cuerpo')->nullable();
            $table->string('estado', 20);
            // Mensaje fijo y sin datos de conexión; nunca el detalle crudo del servidor SMTP.
            $table->string('detalle_error', 255)->nullable();
            $table->timestamp('enviado_en')->nullable();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->index(['empresa_id', 'documento_facturacion_id']);
            $table->index(['empresa_id', 'estado']);

            $table->foreign(['empresa_id', 'documento_facturacion_id'])->references(['empresa_id', 'id'])->on('documentos_facturacion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envios_facturacion');
    }
};
