<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const RESTRICCIONES = [
        'terceros_persona_natural_chk' => "tipo_persona <> 'natural' OR (nombres IS NOT NULL AND razon_social IS NULL AND digito_verificacion IS NULL AND representante_legal IS NULL)",
        'terceros_persona_juridica_chk' => "tipo_persona <> 'juridica' OR (razon_social IS NOT NULL AND digito_verificacion IS NOT NULL AND nombres IS NULL AND apellidos IS NULL)",
        'terceros_documento_persona_chk' => "(tipo_persona = 'juridica') = (tipo_documento = 'nit')",
    ];

    public function up(): void
    {
        Schema::table('terceros', function (Blueprint $table) {
            $table->string('tipo_persona', 20)->nullable(false)->change();
        });

        // SQLite (tests) no admite ADD CONSTRAINT: ahí la coherencia la cubren los FormRequest.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        foreach (self::RESTRICCIONES as $nombre => $condicion) {
            DB::statement("ALTER TABLE terceros ADD CONSTRAINT {$nombre} CHECK ({$condicion})");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            foreach (array_keys(self::RESTRICCIONES) as $nombre) {
                DB::statement("ALTER TABLE terceros DROP CHECK {$nombre}");
            }
        }

        Schema::table('terceros', function (Blueprint $table) {
            $table->string('tipo_persona', 20)->nullable()->change();
        });
    }
};
