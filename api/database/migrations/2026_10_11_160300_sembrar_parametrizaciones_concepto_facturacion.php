<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Una fila por empresa y concepto, todo en cero/false: ningún impuesto activo hasta que se parametrice a mano. */
return new class extends Migration
{
    private const TIPOS_CONCEPTO = ['capital', 'interes_corriente', 'interes_mora', 'otros_gastos'];

    public function up(): void
    {
        $ahora = now();

        foreach (DB::table('empresas')->pluck('id') as $empresaId) {
            foreach (self::TIPOS_CONCEPTO as $tipoConcepto) {
                DB::table('parametrizaciones_concepto_facturacion')->insertOrIgnore([
                    'empresa_id' => $empresaId,
                    'tipo_concepto' => $tipoConcepto,
                    'creado_en' => $ahora,
                    'ultima_actualizacion' => $ahora,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('parametrizaciones_concepto_facturacion')->whereIn('tipo_concepto', self::TIPOS_CONCEPTO)->delete();
    }
};
