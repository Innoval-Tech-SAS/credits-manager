<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El NIT se guardaba junto con el dígito de verificación como "900123456-7"; ahora que digito_verificacion
 * es su propia columna en empresas y empleadores, separa lo ya guardado (mismo criterio que
 * rellenar_tipo_persona_de_terceros, que ya resolvió esto para terceros).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->limpiar('empresas');
        $this->limpiar('empleadores');
    }

    public function down(): void
    {
        // Irreversible a propósito: no se puede reconstruir el formato "nit-dv" original.
    }

    private function limpiar(string $tabla): void
    {
        DB::table($tabla)->whereNotNull('nit')->orderBy('id')->chunkById(500, function ($filas) use ($tabla) {
            foreach ($filas as $fila) {
                if (preg_match('/^([\d.\s]+)-\s*(\d)$/', (string) $fila->nit, $partes)) {
                    DB::table($tabla)->where('id', $fila->id)->update([
                        'nit' => preg_replace('/\D/', '', $partes[1]),
                        'digito_verificacion' => $partes[2],
                    ]);

                    continue;
                }

                $numero = preg_replace('/\D/', '', (string) $fila->nit);

                if ($numero === '') {
                    continue;
                }

                DB::table($tabla)->where('id', $fila->id)->update([
                    'nit' => $numero,
                    'digito_verificacion' => (string) $this->calcularDigitoVerificacion($numero),
                ]);
            }
        });
    }

    /** Algoritmo módulo 11 de la DIAN sobre los dígitos del NIT. */
    private function calcularDigitoVerificacion(string $nit): int
    {
        $pesos = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71];
        $digitos = array_reverse(str_split($nit));

        $suma = 0;
        foreach ($digitos as $posicion => $digito) {
            $suma += (int) $digito * ($pesos[$posicion] ?? 0);
        }

        $residuo = $suma % 11;

        return $residuo > 1 ? 11 - $residuo : $residuo;
    }
};
