<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Único driver que queda es SMTP: la columna no aporta información (siempre valdría 'smtp'). No se borran
 * filas; una fila que haya quedado con `driver = 'registro'` (y por lo tanto sin host, antes opcional para
 * ese driver) la trata la aplicación como "sin configurar" hasta que la empresa la complete con SMTP
 * (ver `ConfiguracionCorreoService::estaLista`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones_correo', function (Blueprint $table) {
            $table->dropColumn('driver');
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_correo', function (Blueprint $table) {
            $table->string('driver', 20)->default('smtp')->after('nombre_remitente');
        });
    }
};
