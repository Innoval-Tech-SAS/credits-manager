<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `es_superadmin` deja de ser columna: lo da el rol Super Admin (ver `Usuario::esSuperadmin`). `es_global` pasa a
 * `es_asesor_global` porque solo tiene sentido en el rol asesor.
 *
 * `clave_superadmin` vale 1 para el único usuario vivo sin empresa y el índice único impide un segundo. Las reglas
 * que cruzan con `roles` van en triggers porque un CHECK no puede consultar otra tabla:
 * - sin empresa ⇔ rol Super Admin (la fila de `roles` sin empresa);
 * - `es_asesor_global` solo con el rol asesor.
 *
 * Todas las operaciones son INSTANT en MySQL 8.4 (renombrar, agregar VIRTUAL, quitar columna): no reconstruyen
 * `usuarios`.
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
        Schema::table('usuarios', function (Blueprint $table) {
            $table->renameColumn('es_global', 'es_asesor_global');
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn('es_superadmin');
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->unsignedTinyInteger('clave_superadmin')
                ->virtualAs('case when empresa_id is null and borrado_en is null then 1 end')
                ->nullable();
            $table->unique('clave_superadmin');
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

        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropUnique(['clave_superadmin']);
            $table->dropColumn('clave_superadmin');
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->boolean('es_superadmin')->default(false)->after('activo');
            $table->renameColumn('es_asesor_global', 'es_global');
        });

        DB::table('usuarios')
            ->whereIn('rol_id', DB::table('roles')->select('id')->whereNull('empresa_id'))
            ->update(['es_superadmin' => true]);
    }
};
