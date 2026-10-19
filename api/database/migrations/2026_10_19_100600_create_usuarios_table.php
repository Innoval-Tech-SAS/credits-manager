<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `empresa_id` nullable: el Super Admin no pertenece a ninguna empresa. `clave_superadmin` es VIRTUAL y vale 1
 * solo para el único usuario vivo sin empresa (el unique impide un segundo). Los triggers de coherencia enlazan
 * `usuarios` con `roles` (un CHECK no puede consultar otra tabla): sin empresa ⇔ rol Super Admin, y
 * `es_asesor_global` solo con el rol asesor.
 */
return new class extends Migration
{
    private const REGLAS = [
        'usuarios_sin_empresa_solo_superadmin' => [
            'condicion' => 'NEW.empresa_id IS NULL AND NOT EXISTS (SELECT 1 FROM roles WHERE roles.id = NEW.rol_id AND roles.empresa_id IS NULL)',
            'mensaje' => 'Un usuario sin empresa solo puede tener el rol Super Admin',
        ],
        'usuarios_superadmin_sin_empresa' => [
            'condicion' => 'NEW.empresa_id IS NOT NULL AND EXISTS (SELECT 1 FROM roles WHERE roles.id = NEW.rol_id AND roles.empresa_id IS NULL)',
            'mensaje' => 'El rol Super Admin no pertenece a ninguna empresa',
        ],
        'usuarios_asesor_global_solo_asesor' => [
            'condicion' => "NEW.es_asesor_global = 1 AND NOT EXISTS (SELECT 1 FROM roles WHERE roles.id = NEW.rol_id AND roles.nombre = 'asesor')",
            'mensaje' => 'Solo un asesor puede tener alcance global',
        ],
    ];

    private const EVENTOS = ['insert' => 'INSERT', 'update' => 'UPDATE'];

    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('usuario')->unique();
            $table->string('password_hash');
            $table->foreignId('rol_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->boolean('es_asesor_global')->default(false);
            $table->unsignedTinyInteger('clave_superadmin')
                ->virtualAs('case when empresa_id is null and borrado_en is null then 1 end')
                ->nullable();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique('clave_superadmin');
            $table->unique(['empresa_id', 'id']);
        });

        foreach (self::REGLAS as $regla => ['condicion' => $condicion, 'mensaje' => $mensaje]) {
            foreach (self::EVENTOS as $sufijo => $evento) {
                DB::unprepared(DB::getDriverName() === 'sqlite'
                    ? "CREATE TRIGGER {$regla}_{$sufijo} BEFORE {$evento} ON usuarios WHEN {$condicion} BEGIN SELECT RAISE(ABORT, '{$mensaje}'); END"
                    : "CREATE TRIGGER {$regla}_{$sufijo} BEFORE {$evento} ON usuarios FOR EACH ROW BEGIN IF {$condicion} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$mensaje}'; END IF; END");
            }
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::REGLAS) as $regla) {
            foreach (array_keys(self::EVENTOS) as $sufijo) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$regla}_{$sufijo}");
            }
        }

        Schema::dropIfExists('usuarios');
    }
};
