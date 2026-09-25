<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Todo cliente tiene un asesor responsable. Los existentes quedan a nombre de quien los creó si es un usuario
 * activo de su empresa; si no, del administrador activo de esa empresa. Cada asignación deja su fila inicial
 * en `cliente_asesor_historial`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->foreignId('asesor_id')->nullable()->after('empleador_id')->constrained('usuarios')->restrictOnDelete();
            $table->index(['empresa_id', 'asesor_id']);
        });

        $this->asignarAsesorAExistentes();

        Schema::table('clientes', function (Blueprint $table) {
            $table->unsignedBigInteger('asesor_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropForeign(['asesor_id']);
            $table->dropIndex(['empresa_id', 'asesor_id']);
            $table->dropColumn('asesor_id');
        });
    }

    private function asignarAsesorAExistentes(): void
    {
        $administradores = DB::table('usuarios')
            ->join('roles', 'roles.id', '=', 'usuarios.rol_id')
            ->where('roles.nombre', 'admin')
            ->where('usuarios.activo', true)
            ->whereNull('usuarios.borrado_en')
            ->orderBy('usuarios.id')
            ->pluck('usuarios.id', 'usuarios.empresa_id');

        $usuariosActivos = DB::table('usuarios')->where('activo', true)->whereNull('borrado_en')->pluck('empresa_id', 'id');

        DB::table('clientes')->select('id', 'empresa_id', 'creado_por')->orderBy('id')->each(function (object $cliente) use ($administradores, $usuariosActivos) {
            $creadorValido = $cliente->creado_por && ($usuariosActivos[$cliente->creado_por] ?? null) === $cliente->empresa_id;
            $asesorId = $creadorValido ? $cliente->creado_por : ($administradores[$cliente->empresa_id] ?? null);

            if (! $asesorId) {
                throw new RuntimeException("La empresa {$cliente->empresa_id} no tiene un administrador activo para asignarle el cliente {$cliente->id}.");
            }

            DB::table('clientes')->where('id', $cliente->id)->update(['asesor_id' => $asesorId]);

            DB::table('cliente_asesor_historial')->insert([
                'empresa_id' => $cliente->empresa_id,
                'cliente_id' => $cliente->id,
                'asesor_anterior_id' => null,
                'asesor_id' => $asesorId,
                'asignado_por' => null,
                'motivo' => 'Asignación inicial de los clientes existentes',
                'creado_en' => now(),
            ]);
        });
    }
};
