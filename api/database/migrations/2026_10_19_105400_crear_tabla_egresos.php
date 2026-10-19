<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Egreso: el pago de uno o más documentos por pagar (`documentos_tercero` con naturaleza `por_pagar`) de un mismo
 * tercero. El cruce contra esos documentos lo deja `aplicaciones_documento`, igual que ya hace Gastos para
 * causarlos — no se repite esa relación aquí. `valor` es la suma de lo aplicado: derivado pero protegido porque se
 * escribe una sola vez, en la misma transacción del Service, y el documento nunca se edita después. `valor_descuento`
 * es la porción de `valor` que no salió de caja (descuento pronto pago); el efectivo real (`valor - valor_descuento`)
 * es derivado, no se guarda aparte. `cuenta_caja_id`/`cuenta_descuento_id` apuntan a `plan_cuentas` y `comprobante_id`
 * a `comprobantes` (módulo Contabilidad, creado más adelante): FK compuesta por empresa igual que en `pagos`→`recibos_caja`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('egresos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('tercero_id')->constrained('terceros')->restrictOnDelete();
            $table->date('fecha');
            $table->unsignedInteger('consecutivo');
            $table->string('numero', 30);
            $table->bigInteger('valor');
            $table->bigInteger('valor_descuento')->default(0);
            $table->string('medio_pago', 20);
            $table->foreignId('cuenta_caja_id')->constrained('plan_cuentas')->restrictOnDelete();
            $table->foreignId('cuenta_descuento_id')->nullable()->constrained('plan_cuentas')->nullOnDelete();
            $table->string('concepto', 255);
            // Nullable: el comprobante se crea después, en la misma transacción, porque su línea de origen necesita el id del egreso.
            $table->unsignedBigInteger('comprobante_id')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique(['empresa_id', 'consecutivo']);
            $table->unique(['empresa_id', 'id']);
            $table->index(['empresa_id', 'tercero_id']);

            $table->foreign(['empresa_id', 'comprobante_id'])->references(['empresa_id', 'id'])->on('comprobantes')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('egresos');
    }
};
