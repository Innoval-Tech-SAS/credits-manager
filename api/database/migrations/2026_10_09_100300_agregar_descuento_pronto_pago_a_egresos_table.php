<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Descuento comercial condicionado (pronto pago): el egreso puede cancelar el valor completo de un documento
 * pagando menos efectivo, con la diferencia acreditada a una cuenta de descuento (ej. 421040). `valor` en `egresos`
 * sigue siendo "lo aplicado" (ver docblock de `create_egresos_table`); `valor_descuento` es la porción de ese valor
 * que no salió de caja — el efectivo real es `valor - valor_descuento`, derivado, no se guarda aparte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('egresos', function (Blueprint $table) {
            $table->bigInteger('valor_descuento')->default(0)->after('valor');
            $table->foreignId('cuenta_descuento_id')->nullable()->after('cuenta_caja_id')->constrained('plan_cuentas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('egresos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cuenta_descuento_id');
            $table->dropColumn('valor_descuento');
        });
    }
};
