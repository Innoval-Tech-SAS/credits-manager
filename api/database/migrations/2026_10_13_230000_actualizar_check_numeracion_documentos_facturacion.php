<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El check original de `2026_10_05_100100` solo contemplaba borrador/emitido. La nota crédito pendiente de
 * aprobación (7.1.2) también se guarda sin consecutivo/número/fecha de emisión, igual que un borrador.
 */
return new class extends Migration
{
    private const CONSTRAINT = 'documentos_facturacion_numeracion_check';

    private const CONDICION_ANTERIOR = "(estado = 'borrador' AND consecutivo IS NULL AND numero IS NULL AND emitido_en IS NULL) OR (estado = 'emitido' AND consecutivo IS NOT NULL AND numero IS NOT NULL AND emitido_en IS NOT NULL)";

    private const CONDICION_NUEVA = "(estado IN ('borrador', 'pendiente_aprobacion') AND consecutivo IS NULL AND numero IS NULL AND emitido_en IS NULL) OR (estado = 'emitido' AND consecutivo IS NOT NULL AND numero IS NOT NULL AND emitido_en IS NOT NULL)";

    public function up(): void
    {
        // SQLite (tests) no admite ADD CONSTRAINT: ahí la coherencia la cubren los Service y los tests.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE documentos_facturacion DROP CHECK '.self::CONSTRAINT);
        DB::statement('ALTER TABLE documentos_facturacion ADD CONSTRAINT '.self::CONSTRAINT.' CHECK ('.self::CONDICION_NUEVA.')');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE documentos_facturacion DROP CHECK '.self::CONSTRAINT);
        DB::statement('ALTER TABLE documentos_facturacion ADD CONSTRAINT '.self::CONSTRAINT.' CHECK ('.self::CONDICION_ANTERIOR.')');
    }
};
