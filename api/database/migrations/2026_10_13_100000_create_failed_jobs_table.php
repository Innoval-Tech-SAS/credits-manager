<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tabla del framework (driver `database-uuids` de config/queue.php): sin ella, un job fallido no queda registrado. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->string('queue', 100)->index();
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
    }
};
