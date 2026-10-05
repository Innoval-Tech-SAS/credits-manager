<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Líneas de los documentos de facturación. La nota crédito apunta a la línea de la factura que corrige
 * (`linea_origen_id`), así la base impide cruzar líneas de otra empresa y queda la trazabilidad por concepto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lineas_documento_facturacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedBigInteger('documento_id');
            $table->unsignedBigInteger('linea_origen_id')->nullable();
            $table->unsignedSmallInteger('orden');
            $table->string('concepto', 60);
            $table->string('descripcion', 255);
            // Centavos, con DineroCast en el Model.
            $table->bigInteger('valor');
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique(['empresa_id', 'id']);
            // Nombres explícitos: MySQL limita los identificadores a 64 caracteres.
            $table->unique(['empresa_id', 'documento_id', 'orden'], 'lineas_fac_orden_unique');
            $table->index(['empresa_id', 'linea_origen_id'], 'lineas_fac_origen_index');

            $table->foreign(['empresa_id', 'documento_id'])->references(['empresa_id', 'id'])->on('documentos_facturacion');
            $table->foreign(['empresa_id', 'linea_origen_id'])->references(['empresa_id', 'id'])->on('lineas_documento_facturacion');
        });

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE lineas_documento_facturacion ADD CONSTRAINT lineas_documento_facturacion_valor_check CHECK (valor > 0)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE lineas_documento_facturacion DROP CHECK lineas_documento_facturacion_valor_check');
        }

        Schema::dropIfExists('lineas_documento_facturacion');
    }
};
