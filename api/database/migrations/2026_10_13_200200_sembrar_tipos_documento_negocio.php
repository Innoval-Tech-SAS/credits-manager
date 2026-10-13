<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Siembra las 6 filas por empresa ya existente, con los valores que reflejan el comportamiento real de hoy. */
return new class extends Migration
{
    private const TIPOS = [
        ['codigo' => 'FV', 'nombre' => 'Factura de venta', 'naturaleza' => 'ingreso', 'crea_saldo' => true, 'cruza_saldo' => false],
        ['codigo' => 'CC', 'nombre' => 'Comprobante de causación', 'naturaleza' => 'gasto', 'crea_saldo' => true, 'cruza_saldo' => false],
        ['codigo' => 'RC', 'nombre' => 'Recibo de caja', 'naturaleza' => 'recaudo', 'crea_saldo' => false, 'cruza_saldo' => true],
        ['codigo' => 'CE', 'nombre' => 'Comprobante de egreso', 'naturaleza' => 'pago', 'crea_saldo' => false, 'cruza_saldo' => true],
        ['codigo' => 'NC', 'nombre' => 'Nota crédito', 'naturaleza' => 'ajuste', 'crea_saldo' => false, 'cruza_saldo' => true],
        ['codigo' => 'ND', 'nombre' => 'Nota débito', 'naturaleza' => 'ajuste', 'crea_saldo' => false, 'cruza_saldo' => true],
    ];

    public function up(): void
    {
        $ahora = now();

        foreach (DB::table('empresas')->pluck('id') as $empresaId) {
            foreach (self::TIPOS as $tipo) {
                DB::table('tipos_documento_negocio')->insertOrIgnore([
                    'empresa_id' => $empresaId,
                    'codigo' => $tipo['codigo'],
                    'nombre' => $tipo['nombre'],
                    'naturaleza' => $tipo['naturaleza'],
                    'crea_saldo' => $tipo['crea_saldo'],
                    'cruza_saldo' => $tipo['cruza_saldo'],
                    'activo' => true,
                    'creado_en' => $ahora,
                    'ultima_actualizacion' => $ahora,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('tipos_documento_negocio')->whereIn('codigo', array_column(self::TIPOS, 'codigo'))->delete();
    }
};
