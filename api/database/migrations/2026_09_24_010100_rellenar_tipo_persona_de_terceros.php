<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Conversión de datos: el documento manda (nit → jurídica; cc/ce/pasaporte → natural), aunque el tercero
 * ya trajera otro `tipo_persona`. Sin dependencias de clases de la app para que la migración no cambie si estas evolucionan.
 * `nombre` no se toca: lo que ya se imprime y se busca queda igual; los nombres y apellidos son una división aproximada.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('terceros')->orderBy('id')->chunkById(500, function ($terceros) {
            foreach ($terceros as $tercero) {
                DB::table('terceros')->where('id', $tercero->id)->update(
                    $tercero->tipo_documento === 'nit'
                        ? $this->datosJuridica($tercero)
                        : $this->datosNatural($tercero),
                );
            }
        });
    }

    public function down(): void
    {
        // Irreversible a propósito: no se puede saber qué tipo_persona tenía cada fila antes.
    }

    /**
     * Un NIT guardado como "900123456-7" se separa en número y dígito de verificación; si el número
     * sin dígito ya existe en la empresa (el índice único cuenta hasta los borrados), se deja como estaba.
     */
    private function datosJuridica(object $tercero): array
    {
        $datos = [
            'tipo_persona' => 'juridica',
            'razon_social' => $tercero->nombre,
            'nombres' => null,
            'apellidos' => null,
        ];

        if (preg_match('/^([\d.\s]+)-\s*(\d)$/', $tercero->numero_documento, $partes)) {
            $numero = preg_replace('/\D/', '', $partes[1]);
            $ocupado = DB::table('terceros')
                ->where('empresa_id', $tercero->empresa_id)
                ->where('tipo_documento', 'nit')
                ->where('numero_documento', $numero)
                ->where('id', '<>', $tercero->id)
                ->exists();

            if (! $ocupado) {
                $datos['numero_documento'] = $numero;
            }

            return $datos + ['digito_verificacion' => $partes[2]];
        }

        return $datos + ['digito_verificacion' => (string) $this->calcularDigitoVerificacion($tercero->numero_documento)];
    }

    /** Convención colombiana: con cuatro o más palabras, las dos primeras son nombres; con menos, la primera. */
    private function datosNatural(object $tercero): array
    {
        $palabras = preg_split('/\s+/', trim($tercero->nombre), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $cantidadNombres = count($palabras) >= 4 ? 2 : 1;

        return [
            'tipo_persona' => 'natural',
            'nombres' => implode(' ', array_slice($palabras, 0, $cantidadNombres)) ?: $tercero->nombre,
            'apellidos' => implode(' ', array_slice($palabras, $cantidadNombres)) ?: null,
            'razon_social' => null,
            'digito_verificacion' => null,
            'representante_legal' => null,
        ];
    }

    /** Algoritmo módulo 11 de la DIAN sobre los dígitos del NIT. */
    private function calcularDigitoVerificacion(string $nit): int
    {
        $pesos = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71];
        $digitos = array_reverse(str_split(preg_replace('/\D/', '', $nit)));

        $suma = 0;
        foreach ($digitos as $posicion => $digito) {
            $suma += (int) $digito * ($pesos[$posicion] ?? 0);
        }

        $residuo = $suma % 11;

        return $residuo > 1 ? 11 - $residuo : $residuo;
    }
};
