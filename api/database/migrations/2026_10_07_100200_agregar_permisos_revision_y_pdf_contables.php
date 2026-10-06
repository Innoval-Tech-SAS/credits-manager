<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permisos por acción que faltaban: `contabilidad.revisar` y `contabilidad.contabilizar` (antes, crear un asiento
 * manual lo contabilizaba de inmediato, con `crear_manual`) e `informes_contables.imprimir` para los PDF de reportes.
 * Conserva el acceso actual: quien tenía `crear_manual` (por rol o por excepción) recibe revisar y contabilizar, y
 * quien tenía `informes_contables.exportar` recibe imprimir. El rol admin recibe los tres. `down()` los quita.
 */
return new class extends Migration
{
    private const NUEVOS = ['contabilidad.revisar', 'contabilidad.contabilizar', 'informes_contables.imprimir'];

    /** Permiso vigente => permisos nuevos que dan la misma capacidad. */
    private const HEREDA = [
        'contabilidad.crear_manual' => ['contabilidad.revisar', 'contabilidad.contabilizar'],
        'informes_contables.exportar' => ['informes_contables.imprimir'],
    ];

    public function up(): void
    {
        foreach (self::NUEVOS as $clave) {
            [$modulo, $accion] = explode('.', $clave);

            DB::table('permisos')->insertOrIgnore([
                'modulo' => $modulo,
                'accion' => $accion,
                'creado_en' => now(),
                'ultima_actualizacion' => now(),
            ]);
        }

        foreach (self::HEREDA as $origen => $nuevos) {
            $origenId = $this->idDe($origen);

            if ($origenId === null) {
                continue;
            }

            foreach (DB::table('rol_permiso')->where('permiso_id', $origenId)->pluck('rol_id') as $rolId) {
                foreach ($nuevos as $nuevo) {
                    $this->asignarARol($rolId, $this->idDe($nuevo));
                }
            }

            foreach (DB::table('usuario_permiso')->where('permiso_id', $origenId)->get(['usuario_id', 'concedido']) as $excepcion) {
                foreach ($nuevos as $nuevo) {
                    DB::table('usuario_permiso')->insertOrIgnore([
                        'usuario_id' => $excepcion->usuario_id,
                        'permiso_id' => $this->idDe($nuevo),
                        'concedido' => (bool) $excepcion->concedido,
                        'creado_en' => now(),
                        'ultima_actualizacion' => now(),
                    ]);
                }
            }
        }

        foreach (DB::table('roles')->where('nombre', 'admin')->pluck('id') as $rolId) {
            foreach (self::NUEVOS as $clave) {
                $this->asignarARol($rolId, $this->idDe($clave));
            }
        }
    }

    public function down(): void
    {
        $permisoIds = DB::table('permisos')
            ->whereIn('modulo', ['contabilidad', 'informes_contables'])
            ->whereIn('accion', ['revisar', 'contabilizar', 'imprimir'])
            ->pluck('id');

        DB::table('rol_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('usuario_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('permisos')->whereIn('id', $permisoIds)->delete();
    }

    private function asignarARol(int $rolId, ?int $permisoId): void
    {
        if ($permisoId === null) {
            return;
        }

        DB::table('rol_permiso')->insertOrIgnore([
            'rol_id' => $rolId,
            'permiso_id' => $permisoId,
            'creado_en' => now(),
            'ultima_actualizacion' => now(),
        ]);
    }

    private function idDe(string $clave): ?int
    {
        [$modulo, $accion] = explode('.', $clave);

        return DB::table('permisos')->where(['modulo' => $modulo, 'accion' => $accion])->value('id');
    }
};
