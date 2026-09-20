<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Datos legacy (de antes de que `TipoDocumentoTercero` se estandarizara a minúsculas)
 * quedaron con 'CC'/'NIT' en mayúsculas. Eloquent no valida el cast de enum al
 * guardar, solo al leer — por eso el dato "entra" pero cualquier listado que
 * serialice esa fila revienta con "X is not a valid backing value for enum".
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('terceros')->update(['tipo_documento' => DB::raw('LOWER(tipo_documento)')]);
    }

    public function down(): void
    {
        // Normalización de datos, sin equivalente inverso significativo.
    }
};
