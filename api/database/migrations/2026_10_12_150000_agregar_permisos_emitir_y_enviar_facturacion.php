<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Emitir una factura (`POST documentos/{id}/emitir`) deja de colgar de `facturacion.crear` y reenviar o reintentar un
 * correo de `facturacion.imprimir`: pasan a `facturacion.emitir` y `facturacion.enviar`. Mismo patrón que
 * `separar_permisos_por_pantalla`: cada uno se copia desde el permiso que lo controlaba, en roles y en excepciones por
 * usuario (mismo `concedido`), así que nadie gana ni pierde acceso al desplegar; el admin recibe ambos. `crear` e
 * `imprimir` siguen vigentes para crear borradores y descargar PDF. `down()` quita lo agregado.
 */
return new class extends Migration
{
    /** Permiso de origen => permisos nuevos que dan exactamente lo mismo que daba el origen en esa operación. */
    private const TRADUCCIONES = [
        'facturacion.crear' => ['facturacion.emitir'],
        'facturacion.imprimir' => ['facturacion.enviar'],
    ];

    public function up(): void
    {
        $nuevos = collect(self::TRADUCCIONES)->flatten()->unique()->values();

        foreach ($nuevos as $clave) {
            [$modulo, $accion] = explode('.', $clave);

            DB::table('permisos')->insertOrIgnore([
                'modulo' => $modulo,
                'accion' => $accion,
                'creado_en' => now(),
                'ultima_actualizacion' => now(),
            ]);
        }

        foreach (self::TRADUCCIONES as $origen => $destinos) {
            $origenId = $this->idDe($origen);

            if ($origenId === null) {
                continue;
            }

            foreach (DB::table('rol_permiso')->where('permiso_id', $origenId)->pluck('rol_id') as $rolId) {
                foreach ($destinos as $destino) {
                    $this->asignarARol((int) $rolId, $this->idDe($destino));
                }
            }

            foreach (DB::table('usuario_permiso')->where('permiso_id', $origenId)->get(['usuario_id', 'concedido']) as $excepcion) {
                foreach ($destinos as $destino) {
                    $this->asignarAUsuario((int) $excepcion->usuario_id, $this->idDe($destino), (bool) $excepcion->concedido);
                }
            }
        }

        foreach (DB::table('roles')->where('nombre', 'admin')->pluck('id') as $rolId) {
            foreach ($nuevos as $clave) {
                $this->asignarARol((int) $rolId, $this->idDe($clave));
            }
        }
    }

    public function down(): void
    {
        $permisoIds = collect(self::TRADUCCIONES)->flatten()->unique()
            ->map(fn (string $clave) => $this->idDe($clave))
            ->filter()
            ->values();

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

    private function asignarAUsuario(int $usuarioId, ?int $permisoId, bool $concedido): void
    {
        if ($permisoId === null) {
            return;
        }

        DB::table('usuario_permiso')->insertOrIgnore([
            'usuario_id' => $usuarioId,
            'permiso_id' => $permisoId,
            'concedido' => $concedido,
            'creado_en' => now(),
            'ultima_actualizacion' => now(),
        ]);
    }

    private function idDe(string $clave): ?int
    {
        [$modulo, $accion] = explode('.', $clave);
        $id = DB::table('permisos')->where(['modulo' => $modulo, 'accion' => $accion])->value('id');

        return $id === null ? null : (int) $id;
    }
};
