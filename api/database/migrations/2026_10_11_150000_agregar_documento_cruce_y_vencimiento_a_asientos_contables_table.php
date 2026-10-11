<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asientos_contables', function (Blueprint $table) {
            $table->string('documento_cruce', 60)->nullable()->after('centro_costo_id');
            $table->date('fecha_vencimiento')->nullable()->after('documento_cruce');
        });
    }

    public function down(): void
    {
        Schema::table('asientos_contables', function (Blueprint $table) {
            $table->dropColumn(['documento_cruce', 'fecha_vencimiento']);
        });
    }
};
