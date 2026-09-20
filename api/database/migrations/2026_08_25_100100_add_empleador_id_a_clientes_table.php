<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->foreignId('empleador_id')->nullable()->after('empleador')->constrained('empleadores')->nullOnDelete();
        });

        $empresaId = DB::table('empresas')->value('id');

        if ($empresaId) {
            $nombres = DB::table('clientes')->whereNotNull('empleador')->where('empleador', '!=', '')->distinct()->pluck('empleador');

            foreach ($nombres as $nombre) {
                $empleadorId = DB::table('empleadores')->insertGetId([
                    'empresa_id' => $empresaId,
                    'nombre' => $nombre,
                    'creado_en' => now(),
                    'ultima_actualizacion' => now(),
                ]);

                DB::table('clientes')->where('empleador', $nombre)->update(['empleador_id' => $empleadorId]);
            }
        }

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('empleador');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('empleador')->nullable()->after('numero_documento');
        });

        DB::table('clientes')
            ->join('empleadores', 'empleadores.id', '=', 'clientes.empleador_id')
            ->update(['clientes.empleador' => DB::raw('empleadores.nombre')]);

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('empleador_id');
        });
    }
};
