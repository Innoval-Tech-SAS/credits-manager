<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Amplía la unicidad del consecutivo para que el reinicio periódico pueda repetir el número en ciclos
 * distintos: `(empresa_id, tipo_comprobante_id, ciclo_numeracion, consecutivo)` en vez de solo
 * `(empresa_id, tipo_comprobante_id, consecutivo)` (el real hoy — ver `2026_08_31_011000_add_empresa_id_a_
 * comprobantes_table.php`, que le agregó `empresa_id` al único original). `ciclo_numeracion` default ''
 * (no null) para los tipos sin reinicio, igual que `tipos_comprobante.ciclo_numeracion_actual` — así el
 * `unique` compuesto sigue protegiendo la duplicidad de siempre (NULL no se compara como igual a NULL en
 * un índice único).
 *
 * No hace falta un índice de respaldo nuevo para la FK de `tipo_comprobante_id`: esa migración de 2026-08-31
 * ya dejó uno permanente (`comprobantes_tipo_comprobante_id_index`), con el mismo razonamiento que este
 * comentario explica ("MySQL usa el único... para respaldar la FK... sin un índice propio, no deja
 * borrarlo").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->string('ciclo_numeracion', 10)->default('')->after('consecutivo');
        });

        Schema::table('comprobantes', function (Blueprint $table) {
            $table->dropUnique(['empresa_id', 'tipo_comprobante_id', 'consecutivo']);
            // Nombre explícito y corto: el que generaría Laravel por las 4 columnas supera el límite de 64
            // caracteres de un identificador en MySQL.
            $table->unique(['empresa_id', 'tipo_comprobante_id', 'ciclo_numeracion', 'consecutivo'], 'comprobantes_consecutivo_por_ciclo_unique');
        });
    }

    public function down(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->unique(['empresa_id', 'tipo_comprobante_id', 'consecutivo']);
        });

        Schema::table('comprobantes', function (Blueprint $table) {
            $table->dropUnique('comprobantes_consecutivo_por_ciclo_unique');
            $table->dropColumn('ciclo_numeracion');
        });
    }
};
