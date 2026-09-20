<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL usa el único [tipo_comprobante_id, consecutivo] para respaldar la FK
        // de tipo_comprobante_id: sin un índice propio, no deja borrarlo.
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->index('tipo_comprobante_id', 'comprobantes_tipo_comprobante_id_index');
        });

        Schema::table('comprobantes', function (Blueprint $table) {
            $table->dropUnique(['tipo_comprobante_id', 'consecutivo']);
            $table->foreignId('empresa_id')->after('id')->constrained('empresas')->cascadeOnDelete();
            $table->unique(['empresa_id', 'tipo_comprobante_id', 'consecutivo']);
        });
    }

    public function down(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->dropUnique(['empresa_id', 'tipo_comprobante_id', 'consecutivo']);
            $table->dropConstrainedForeignId('empresa_id');
            $table->unique(['tipo_comprobante_id', 'consecutivo']);
        });

        Schema::table('comprobantes', function (Blueprint $table) {
            $table->dropIndex('comprobantes_tipo_comprobante_id_index');
        });
    }
};
