<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `actividad_economica_id` apunta a `actividades_economicas` (módulo Gastos/tributario, creada más adelante en el
 * orden de migraciones): FK nullable declarada igual, el orden final de ejecución la resuelve el `empresa_id` que
 * ya existe en ambas. Los 3 CHECK exigen coherencia natural/jurídica y que el tipo de documento concuerde.
 */
return new class extends Migration
{
    private const RESTRICCIONES = [
        'terceros_persona_natural_chk' => "tipo_persona <> 'natural' OR (nombres IS NOT NULL AND razon_social IS NULL AND digito_verificacion IS NULL AND representante_legal IS NULL)",
        'terceros_persona_juridica_chk' => "tipo_persona <> 'juridica' OR (razon_social IS NOT NULL AND digito_verificacion IS NOT NULL AND nombres IS NULL AND apellidos IS NULL)",
        'terceros_documento_persona_chk' => "(tipo_persona = 'juridica') = (tipo_documento = 'nit')",
    ];

    public function up(): void
    {
        Schema::create('terceros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('tipo_documento', 15);
            $table->string('numero_documento', 30);
            $table->string('nombre', 150);
            $table->string('nombres', 100)->nullable();
            $table->string('apellidos', 100)->nullable();
            $table->string('razon_social', 150)->nullable();
            $table->string('digito_verificacion', 1)->nullable();
            $table->string('representante_legal', 150)->nullable();
            $table->string('direccion')->nullable();
            $table->string('barrio')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('celular', 30)->nullable();
            $table->string('telefono_fijo', 30)->nullable();
            $table->string('correo', 120)->nullable();
            $table->string('regimen_tributario', 60)->nullable();
            $table->string('tipo_persona', 20);
            $table->boolean('autorretenedor')->default(false);
            $table->boolean('responsable_iva')->default(false);
            $table->foreignId('actividad_economica_id')->nullable()->constrained('actividades_economicas')->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique(['empresa_id', 'tipo_documento', 'numero_documento']);
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
        Schema::dropIfExists('terceros');
    }
};
