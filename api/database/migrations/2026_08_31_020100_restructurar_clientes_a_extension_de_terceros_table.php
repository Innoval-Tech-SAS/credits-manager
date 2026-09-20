<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mismo caso que en comprobantes: aseguramos un índice propio para la FK de
        // empresa_id antes de borrar el único que hoy la respalda en MySQL.
        Schema::table('clientes', function (Blueprint $table) {
            $table->index('empresa_id', 'clientes_empresa_id_index');
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique(['empresa_id', 'numero_documento']);
            $table->foreignId('tercero_id')->after('empresa_id')->constrained('terceros');
            $table->dropColumn([
                'nombre', 'tipo_documento', 'numero_documento',
                'direccion', 'barrio', 'ciudad', 'celular', 'telefono_fijo', 'correo',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('nombre')->after('tercero_id');
            $table->string('tipo_documento')->after('nombre');
            $table->string('numero_documento')->after('tipo_documento');
            $table->string('direccion')->nullable()->after('numero_documento');
            $table->string('barrio')->nullable()->after('direccion');
            $table->string('ciudad')->nullable()->after('barrio');
            $table->string('celular')->nullable()->after('ciudad');
            $table->string('telefono_fijo')->nullable()->after('celular');
            $table->string('correo')->after('telefono_fijo');
            $table->dropConstrainedForeignId('tercero_id');
            $table->unique(['empresa_id', 'numero_documento']);
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropIndex('clientes_empresa_id_index');
        });
    }
};
