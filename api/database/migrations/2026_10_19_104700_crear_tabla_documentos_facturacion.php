<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Facturas, notas crédito y notas débito internas. Un solo esquema para los tres tipos: la nota apunta a su
 * documento de origen y hereda el crédito. El número existe solo desde la emisión (borrador y nota pendiente
 * de aprobación, sin número).
 */
return new class extends Migration
{
    /** Invariantes que la base sí puede expresar. Las demás (montos acumulados, líneas no vacías) las cubre el Service. */
    private const RESTRICCIONES = [
        'documentos_facturacion_numeracion_check' => "(estado IN ('borrador', 'pendiente_aprobacion') AND consecutivo IS NULL AND numero IS NULL AND emitido_en IS NULL) OR (estado = 'emitido' AND consecutivo IS NOT NULL AND numero IS NOT NULL AND emitido_en IS NOT NULL)",
        'documentos_facturacion_origen_check' => "(tipo = 'factura' AND documento_origen_id IS NULL) OR (tipo <> 'factura' AND documento_origen_id IS NOT NULL)",
    ];

    public function up(): void
    {
        Schema::create('documentos_facturacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('tipo', 20);
            $table->string('estado', 20);
            $table->unsignedInteger('consecutivo')->nullable();
            // Número tal como se imprimió: prefijo y consecutivo congelados al emitir.
            $table->string('numero', 30)->nullable();
            $table->date('fecha');
            $table->date('fecha_vencimiento')->nullable();
            $table->unsignedBigInteger('cliente_id');
            $table->unsignedBigInteger('credito_id');
            $table->unsignedBigInteger('documento_origen_id')->nullable();
            $table->string('motivo', 500)->nullable();
            $table->foreignId('solicitado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('motivo_codigo', 30)->nullable();
            $table->timestamp('emitido_en')->nullable();
            $table->foreignId('emitido_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->unsignedBigInteger('comprobante_id')->nullable();
            $table->unsignedBigInteger('documento_tercero_id')->nullable();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique(['empresa_id', 'id']);
            // Varias filas con consecutivo NULL (borradores y pendientes de aprobación) son válidas en MySQL.
            $table->unique(['empresa_id', 'tipo', 'consecutivo']);
            $table->index(['empresa_id', 'estado']);
            $table->index(['empresa_id', 'cliente_id']);
            $table->index(['empresa_id', 'credito_id']);
            $table->index(['empresa_id', 'documento_origen_id']);
            $table->index(['empresa_id', 'motivo_codigo']);

            $table->foreign(['empresa_id', 'cliente_id'])->references(['empresa_id', 'id'])->on('clientes');
            $table->foreign(['empresa_id', 'credito_id'])->references(['empresa_id', 'id'])->on('creditos');
            $table->foreign(['empresa_id', 'documento_origen_id'])->references(['empresa_id', 'id'])->on('documentos_facturacion');
            $table->foreign(['empresa_id', 'comprobante_id'])->references(['empresa_id', 'id'])->on('comprobantes');
            $table->foreign(['empresa_id', 'documento_tercero_id'])->references(['empresa_id', 'id'])->on('documentos_tercero');
        });

        // SQLite (tests) no admite ADD CONSTRAINT: ahí la coherencia la cubren los Service y los tests.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        foreach (self::RESTRICCIONES as $nombre => $condicion) {
            DB::statement("ALTER TABLE documentos_facturacion ADD CONSTRAINT {$nombre} CHECK ({$condicion})");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            foreach (array_keys(self::RESTRICCIONES) as $nombre) {
                DB::statement("ALTER TABLE documentos_facturacion DROP CHECK {$nombre}");
            }
        }

        Schema::dropIfExists('documentos_facturacion');
    }
};
