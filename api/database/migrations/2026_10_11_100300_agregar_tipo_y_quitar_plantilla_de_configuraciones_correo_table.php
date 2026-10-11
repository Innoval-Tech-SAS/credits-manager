<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Separa la configuración de correo por tipo (`cobranza`/`facturacion`): antes había una sola fila por empresa que
 * facturación reutilizaba solo para las credenciales del servidor. Sin datos reales que preservar (proyecto aún sin
 * producción): las filas existentes quedan como `cobranza` (el DEFAULT es solo para no dejarlas en null; el Service
 * siempre indica el tipo explícito al escribir) y las plantillas se recrean desde cero en `plantillas_correo`.
 * El DEFAULT se deja puesto a propósito: quitarlo requiere `ALTER COLUMN ... DROP DEFAULT`, sintaxis que SQLite (el
 * motor de los tests) no soporta.
 *
 * `unique('empresa_id')` es el único índice que respalda la FK de `empresa_id` (se creó en la misma columna que la
 * constraint). MySQL no deja soltar ese índice mientras sea el único que la respalda (error 1553): hay que crear
 * primero el índice compuesto `(empresa_id, tipo)` — también respalda la FK, porque `empresa_id` es su columna
 * líder — y solo después soltar el único viejo. El `down()` hace el mismo baile al revés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones_correo', function (Blueprint $table) {
            $table->string('tipo', 20)->default('cobranza')->after('empresa_id');
        });

        Schema::table('configuraciones_correo', function (Blueprint $table) {
            $table->unique(['empresa_id', 'tipo']);
        });

        Schema::table('configuraciones_correo', function (Blueprint $table) {
            $table->dropUnique(['empresa_id']);
            $table->dropColumn(['plantilla_asunto', 'plantilla_cuerpo']);
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_correo', function (Blueprint $table) {
            $table->string('plantilla_asunto', 200)->nullable();
            $table->longText('plantilla_cuerpo')->nullable();
            $table->unique('empresa_id');
        });

        Schema::table('configuraciones_correo', function (Blueprint $table) {
            $table->dropUnique(['empresa_id', 'tipo']);
        });

        Schema::table('configuraciones_correo', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
