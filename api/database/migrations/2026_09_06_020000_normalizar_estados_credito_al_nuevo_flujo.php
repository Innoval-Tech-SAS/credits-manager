<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `EstadoCredito` pasa de Activo/Pagado/Anulado a un flujo completo
 * Solicitado/Aprobado/Desembolsado/Pagado/Cancelado. Los créditos existentes en
 * 'activo' (en curso, ya desembolsados) pasan a 'desembolsado'; los 'anulado'
 * (caso sin uso real) pasan a 'cancelado'.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('creditos')->where('estado', 'activo')->update(['estado' => 'desembolsado']);
        DB::table('creditos')->where('estado', 'anulado')->update(['estado' => 'cancelado']);
    }

    public function down(): void
    {
        DB::table('creditos')->where('estado', 'desembolsado')->update(['estado' => 'activo']);
        DB::table('creditos')->where('estado', 'cancelado')->update(['estado' => 'anulado']);
    }
};
