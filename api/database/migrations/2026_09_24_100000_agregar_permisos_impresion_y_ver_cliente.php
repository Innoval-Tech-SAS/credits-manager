<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mismo patrón que `agregar_permisos_exportar_reportes`: el catálogo y PERMISOS_POR_ROL solo aplican al crear
 * una empresa. Cada permiso nuevo se concede a los roles (y a las excepciones por usuario) que hoy ya tienen
 * el permiso con el que se protegía esa acción, para que nadie gane ni pierda acceso al desplegar.
 */
return new class extends Migration
{
    /** 'modulo.accion' nuevo => permisos vigentes hasta ahora que daban esa acción. */
    private const ORIGENES = [
        'clientes.ver' => ['clientes.listar', 'clientes.editar'],
        'creditos.imprimir_estado_cuenta' => ['creditos.listar'],
        'pagos.imprimir_recibo' => ['pagos.listar'],
        'contabilidad.imprimir_comprobante' => ['contabilidad.listar'],
        'cartera.imprimir_reporte_empleador' => ['cartera.exportar'],
    ];

    public function up(): void
    {
        foreach (self::ORIGENES as $nuevo => $origenes) {
            $nuevoId = $this->asegurarPermiso($nuevo);
            $origenIds = array_filter(array_map(fn (string $clave) => $this->idDe($clave), $origenes));

            foreach (DB::table('rol_permiso')->whereIn('permiso_id', $origenIds)->distinct()->pluck('rol_id') as $rolId) {
                DB::table('rol_permiso')->insertOrIgnore([
                    'rol_id' => $rolId,
                    'permiso_id' => $nuevoId,
                    'creado_en' => now(),
                    'ultima_actualizacion' => now(),
                ]);
            }

            $excepciones = DB::table('usuario_permiso')->whereIn('permiso_id', $origenIds)->get();

            foreach ($excepciones->groupBy('usuario_id') as $usuarioId => $filas) {
                // Concedido si alguno de los orígenes lo concede; revocado solo si todos lo revocan.
                DB::table('usuario_permiso')->insertOrIgnore([
                    'usuario_id' => $usuarioId,
                    'permiso_id' => $nuevoId,
                    'concedido' => $filas->contains(fn ($fila) => (bool) $fila->concedido),
                    'creado_en' => now(),
                    'ultima_actualizacion' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $permisoIds = collect(array_keys(self::ORIGENES))->map(fn (string $clave) => $this->idDe($clave))->filter();

        DB::table('rol_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('usuario_permiso')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('permisos')->whereIn('id', $permisoIds)->delete();
    }

    private function asegurarPermiso(string $clave): int
    {
        [$modulo, $accion] = explode('.', $clave);

        DB::table('permisos')->updateOrInsert(
            ['modulo' => $modulo, 'accion' => $accion],
            ['creado_en' => now(), 'ultima_actualizacion' => now()],
        );

        return $this->idDe($clave);
    }

    private function idDe(string $clave): ?int
    {
        [$modulo, $accion] = explode('.', $clave);

        return DB::table('permisos')->where(['modulo' => $modulo, 'accion' => $accion])->value('id');
    }
};
