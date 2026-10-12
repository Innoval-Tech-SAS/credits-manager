<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permisos granulares para operaciones que se autorizaban con el permiso de otro módulo, uno genérico o solo con el
 * rol admin. Mismo patrón que `separar_permiso_anular_notas_facturacion`: quien tenía el permiso de origen recibe el
 * nuevo en roles y en excepciones por usuario (mismo `concedido`), así nadie gana ni pierde acceso al desplegar.
 * Lo que antes exigía el middleware `admin` se concede solo a los roles admin.
 */
return new class extends Migration
{
    /** Permiso nuevo => permiso de origen cuyo acceso hereda. */
    private const COPIAS = [
        'empleadores.listar' => 'clientes.listar',
        'empleadores.ver' => 'clientes.ver',
        'empleadores.crear' => 'clientes.crear',
        'empleadores.editar' => 'clientes.editar',
        'facturacion.registrar_cobro' => 'facturacion.crear',
        'facturacion.reversar_cobro' => 'facturacion.anular',
    ];

    private const SOLO_ADMIN = ['empleadores.eliminar', 'clientes.eliminar', 'clientes.reasignar_asesor'];

    public function up(): void
    {
        foreach ([...array_keys(self::COPIAS), ...self::SOLO_ADMIN] as $clave) {
            [$modulo, $accion] = explode('.', $clave);

            DB::table('permisos')->insertOrIgnore([
                'modulo' => $modulo,
                'accion' => $accion,
                'creado_en' => now(),
                'ultima_actualizacion' => now(),
            ]);
        }

        foreach (self::COPIAS as $nueva => $origen) {
            $nuevoId = $this->idPermiso($nueva);
            $origenId = $this->idPermiso($origen);

            if ($nuevoId === null || $origenId === null) {
                continue;
            }

            foreach (DB::table('rol_permiso')->where('permiso_id', $origenId)->pluck('rol_id') as $rolId) {
                $this->asignarARol($rolId, $nuevoId);
            }

            foreach (DB::table('usuario_permiso')->where('permiso_id', $origenId)->get(['usuario_id', 'concedido']) as $excepcion) {
                DB::table('usuario_permiso')->insertOrIgnore([
                    'usuario_id' => $excepcion->usuario_id,
                    'permiso_id' => $nuevoId,
                    'concedido' => $excepcion->concedido,
                    'creado_en' => now(),
                    'ultima_actualizacion' => now(),
                ]);
            }
        }

        $rolesAdmin = DB::table('roles')->where('nombre', 'admin')->pluck('id');

        foreach (self::SOLO_ADMIN as $clave) {
            $permisoId = $this->idPermiso($clave);

            foreach ($rolesAdmin as $rolId) {
                $this->asignarARol($rolId, $permisoId);
            }
        }
    }

    public function down(): void
    {
        $permisoIds = collect([...array_keys(self::COPIAS), ...self::SOLO_ADMIN])
            ->map(fn (string $clave) => $this->idPermiso($clave))
            ->filter()
            ->values();

        DB::table('rol_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('usuario_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('permisos')->whereIn('id', $permisoIds)->delete();
    }

    private function idPermiso(string $clave): ?int
    {
        [$modulo, $accion] = explode('.', $clave);

        $id = DB::table('permisos')->where(['modulo' => $modulo, 'accion' => $accion])->value('id');

        return $id === null ? null : (int) $id;
    }

    private function asignarARol(int $rolId, int $permisoId): void
    {
        DB::table('rol_permiso')->insertOrIgnore([
            'rol_id' => $rolId,
            'permiso_id' => $permisoId,
            'creado_en' => now(),
            'ultima_actualizacion' => now(),
        ]);
    }
};
