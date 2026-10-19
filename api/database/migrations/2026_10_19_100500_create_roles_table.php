<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `empresa_id` nullable: el rol Super Admin es la única fila sin empresa. `clave_plataforma` es VIRTUAL y vale 1
 * solo para esa fila (los roles de empresa quedan en NULL, que no choca con el unique).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('descripcion')->nullable();
            $table->unsignedTinyInteger('clave_plataforma')
                ->virtualAs('case when empresa_id is null then 1 end')
                ->nullable();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique(['empresa_id', 'nombre']);
            $table->unique('clave_plataforma');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
