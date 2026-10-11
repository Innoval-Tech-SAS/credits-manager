<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La UVT deja de ser una constante nacional compartida (sin `empresa_id`) y pasa a ser por-empresa: cada una
 * la administra por su cuenta, para que un admin no pueda alterar el valor que usan las demás. Duplica cada
 * valor ya guardado en todas las empresas, para no dejar sin UVT configurada a las que ya dependían de él.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('valores_uvt', function (Blueprint $table) {
            $table->foreignId('empresa_id')->nullable()->after('id')->constrained('empresas')->cascadeOnDelete();
            $table->dropUnique(['anio']);
        });

        $this->duplicarPorEmpresa();

        Schema::table('valores_uvt', function (Blueprint $table) {
            $table->unsignedBigInteger('empresa_id')->nullable(false)->change();
            $table->unique(['empresa_id', 'anio']);
        });
    }

    /**
     * No restaura `unique(anio)`: tras duplicar cada valor en todas las empresas, el mismo año se repite
     * a propósito en varias filas, así que esa restricción ya no se puede cumplir sin perder datos.
     */
    public function down(): void
    {
        Schema::table('valores_uvt', function (Blueprint $table) {
            $table->dropForeign(['empresa_id']);
            $table->dropUnique(['empresa_id', 'anio']);
            $table->dropColumn('empresa_id');
        });
    }

    private function duplicarPorEmpresa(): void
    {
        $empresas = DB::table('empresas')->orderBy('id')->pluck('id');
        $filas = DB::table('valores_uvt')->orderBy('id')->get();

        foreach ($filas as $fila) {
            foreach ($empresas as $indice => $empresaId) {
                if ($indice === 0) {
                    DB::table('valores_uvt')->where('id', $fila->id)->update(['empresa_id' => $empresaId]);

                    continue;
                }

                DB::table('valores_uvt')->insert([
                    'empresa_id' => $empresaId,
                    'anio' => $fila->anio,
                    'valor' => $fila->valor,
                    'creado_en' => $fila->creado_en,
                    'ultima_actualizacion' => $fila->ultima_actualizacion,
                    'borrado_en' => $fila->borrado_en,
                ]);
            }
        }

        DB::table('valores_uvt')->whereNull('empresa_id')->delete();
    }
};
