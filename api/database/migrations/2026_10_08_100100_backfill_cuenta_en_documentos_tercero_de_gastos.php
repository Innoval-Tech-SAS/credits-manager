<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Completa `plan_cuenta_id` en los documentos de tercero ya creados por Gastos, con la cuenta CxP de su
 * parametrización vigente al causar. En PHP (no UPDATE...JOIN) porque SQLite, usado en los tests, no admite join en UPDATE.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('gastos')
            ->join('parametrizaciones_tipo_gasto', 'parametrizaciones_tipo_gasto.id', '=', 'gastos.parametrizacion_tipo_gasto_id')
            ->whereNotNull('gastos.documento_tercero_id')
            ->select('gastos.documento_tercero_id', 'parametrizaciones_tipo_gasto.cuenta_cxp_id')
            ->orderBy('gastos.id')
            ->chunk(500, function ($filas) {
                foreach ($filas as $fila) {
                    DB::table('documentos_tercero')
                        ->where('id', $fila->documento_tercero_id)
                        ->whereNull('plan_cuenta_id')
                        ->update(['plan_cuenta_id' => $fila->cuenta_cxp_id]);
                }
            });
    }

    public function down(): void
    {
        // Dato derivado: no hay nada que revertir de forma segura sin perder el origen de otros módulos.
    }
};
