<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terceros', function (Blueprint $table) {
            $table->string('regimen_tributario', 60)->nullable()->after('correo');
            $table->string('tipo_persona', 20)->nullable()->after('regimen_tributario');
            $table->boolean('autorretenedor')->default(false)->after('tipo_persona');
            $table->boolean('responsable_iva')->default(false)->after('autorretenedor');
            $table->foreignId('actividad_economica_id')->nullable()->after('responsable_iva')
                ->constrained('actividades_economicas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('terceros', function (Blueprint $table) {
            $table->dropConstrainedForeignId('actividad_economica_id');
            $table->dropColumn(['regimen_tributario', 'tipo_persona', 'autorretenedor', 'responsable_iva']);
        });
    }
};
