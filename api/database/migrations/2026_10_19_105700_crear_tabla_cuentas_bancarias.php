<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `plan_cuenta_id` es la cuenta PUC que ya recibe los movimientos de este banco (Egresos, recaudos): esta tabla
 * vincula esa cuenta con los datos del banco, no crea un mecanismo de contabilización nuevo. `plan_cuenta_puente_id`
 * es la cuenta "por identificar" para movimientos del extracto sin origen en el libro; nada impide que varias
 * cuentas bancarias compartan la misma cuenta puente si la empresa prefiere una sola.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuentas_bancarias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('banco', 30);
            $table->string('numero_cuenta', 30);
            $table->string('tipo_cuenta', 20);
            $table->string('alias', 80)->nullable();
            $table->foreignId('plan_cuenta_id')->constrained('plan_cuentas');
            $table->foreignId('plan_cuenta_puente_id')->nullable()->constrained('plan_cuentas');
            $table->boolean('activa')->default(true);
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique(['empresa_id', 'numero_cuenta']);
            $table->unique(['empresa_id', 'id']);
            $table->index(['empresa_id', 'activa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas_bancarias');
    }
};
