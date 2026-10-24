<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de solo inserción. Una marca no se edita ni se borra: desmarcar es una fila `reversa` que apunta a la
 * marca original (marca_origen_id, único: una marca se reversa una sola vez). `asiento_contable_id` no se
 * compone con empresa_id porque `asientos_contables` no tiene esa columna en ningún lugar del proyecto (se
 * filtra siempre por join contra `comprobantes`); el Service valida la pertenencia a la empresa por ese mismo
 * camino.
 */
return new class extends Migration
{
    private const MENSAJE = 'marcas_conciliacion es de solo insercion';

    private const RESTRICCIONES = [
        'marcas_conciliacion_valor_check' => 'valor > 0',
        'marcas_conciliacion_tipo_check' => "tipo IN ('conciliado', 'puente', 'reversa')",
        'marcas_conciliacion_reversa_check' => "(tipo = 'reversa' AND marca_origen_id IS NOT NULL) OR (tipo != 'reversa' AND marca_origen_id IS NULL)",
    ];

    public function up(): void
    {
        Schema::create('marcas_conciliacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedBigInteger('movimiento_extracto_id');
            $table->foreignId('asiento_contable_id')->nullable()->constrained('asientos_contables');
            $table->unsignedBigInteger('comprobante_generado_id')->nullable();
            $table->string('tipo', 20);
            $table->unsignedBigInteger('marca_origen_id')->nullable();
            $table->bigInteger('valor');
            $table->string('observacion', 255)->nullable();
            $table->foreignId('marcado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('creado_en')->nullable();

            $table->unique(['empresa_id', 'id']);
            $table->unique(['empresa_id', 'marca_origen_id'], 'marcas_conciliacion_reversa_unique');
            $table->index(['empresa_id', 'movimiento_extracto_id'], 'marcas_conciliacion_movimiento_index');

            $table->foreign(['empresa_id', 'movimiento_extracto_id'])->references(['empresa_id', 'id'])->on('movimientos_extracto');
            $table->foreign(['empresa_id', 'comprobante_generado_id'])->references(['empresa_id', 'id'])->on('comprobantes');
            $table->foreign(['empresa_id', 'marca_origen_id'])->references(['empresa_id', 'id'])->on('marcas_conciliacion');
        });

        $mensaje = self::MENSAJE;

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER marcas_conciliacion_sin_update BEFORE UPDATE ON marcas_conciliacion BEGIN SELECT RAISE(ABORT, '{$mensaje}'); END");
            DB::unprepared("CREATE TRIGGER marcas_conciliacion_sin_delete BEFORE DELETE ON marcas_conciliacion BEGIN SELECT RAISE(ABORT, '{$mensaje}'); END");

            return;
        }

        DB::unprepared("CREATE TRIGGER marcas_conciliacion_sin_update BEFORE UPDATE ON marcas_conciliacion FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$mensaje}'");
        DB::unprepared("CREATE TRIGGER marcas_conciliacion_sin_delete BEFORE DELETE ON marcas_conciliacion FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$mensaje}'");

        foreach (self::RESTRICCIONES as $nombre => $condicion) {
            DB::statement("ALTER TABLE marcas_conciliacion ADD CONSTRAINT {$nombre} CHECK ({$condicion})");
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS marcas_conciliacion_sin_update');
        DB::unprepared('DROP TRIGGER IF EXISTS marcas_conciliacion_sin_delete');

        Schema::dropIfExists('marcas_conciliacion');
    }
};
