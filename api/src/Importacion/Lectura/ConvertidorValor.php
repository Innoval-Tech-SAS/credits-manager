<?php

namespace App\Importacion\Lectura;

use App\Core\Dinero\Dinero;
use App\Importacion\Data\FilaRechazada;
use Carbon\CarbonImmutable;

/** Convierte el texto de una celda a su tipo. Nunca adivina: un formato no reconocido es un rechazo de la fila. */
final class ConvertidorValor
{
    /** Número de serie de Excel 1 = 1900-01-01; a partir de 1900-03-01 la base 1899-12-30 es exacta. */
    private const BASE_SERIE_EXCEL = '1899-12-30';

    private const SERIE_MAXIMA = 2958465;

    public static function fecha(string $valor, string $columna): ?string
    {
        $texto = trim($valor);

        if ($texto === '') {
            return null;
        }

        if (preg_match('/^\d{1,6}(\.0+)?$/', $texto) === 1) {
            $serie = (int) $texto;

            if ($serie < 1 || $serie > self::SERIE_MAXIMA) {
                throw new FilaRechazada("Número de serie de fecha fuera de rango: {$texto}.", $columna);
            }

            return CarbonImmutable::parse(self::BASE_SERIE_EXCEL)->addDays($serie)->toDateString();
        }

        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $texto, $partes) === 1 && checkdate((int) $partes[2], (int) $partes[1], (int) $partes[3])) {
            return sprintf('%04d-%02d-%02d', $partes[3], $partes[2], $partes[1]);
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $texto, $partes) === 1 && checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1])) {
            return $texto;
        }

        throw new FilaRechazada("Fecha no reconocida: «{$texto}». Usa dd/mm/aaaa, aaaa-mm-dd o el número de serie de Excel.", $columna);
    }

    /** Montos en formato colombiano (2.000.000 o 2.000.000,50) o con punto decimal (2000000.50). */
    public static function monto(string $valor, string $columna): ?Dinero
    {
        $texto = str_replace([' ', '$', "\u{00A0}"], '', trim($valor));

        if ($texto === '') {
            return null;
        }

        if (str_contains($texto, ',')) {
            if (preg_match('/^\d{1,3}(\.\d{3})*,\d{1,2}$|^\d+,\d{1,2}$/', $texto) !== 1) {
                throw new FilaRechazada("Monto no reconocido: «{$valor}».", $columna);
            }

            $normalizado = str_replace(',', '.', str_replace('.', '', $texto));
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $texto) === 1) {
            $normalizado = str_replace('.', '', $texto);
        } elseif (preg_match('/^\d+(\.\d{1,2})?$/', $texto) === 1) {
            $normalizado = $texto;
        } else {
            throw new FilaRechazada("Monto no reconocido: «{$valor}». Usa 2.000.000, 2.000.000,50 o 2000000.50.", $columna);
        }

        return Dinero::desdePesos($normalizado);
    }

    /** Tasas y otros porcentajes: decimal con coma o punto, sin separador de miles. */
    public static function decimal(string $valor, string $columna): ?string
    {
        $texto = str_replace(',', '.', trim($valor));

        if ($texto === '') {
            return null;
        }

        if (preg_match('/^\d+(\.\d{1,4})?$/', $texto) !== 1) {
            throw new FilaRechazada("Número no reconocido: «{$valor}».", $columna);
        }

        return $texto;
    }

    public static function entero(string $valor, string $columna): ?int
    {
        $texto = trim($valor);

        if ($texto === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $texto) !== 1) {
            throw new FilaRechazada("Número entero no reconocido: «{$valor}».", $columna);
        }

        return (int) $texto;
    }

    public static function siNo(string $valor, string $columna): ?bool
    {
        $texto = self::sinAcentos(mb_strtolower(trim($valor)));

        return match ($texto) {
            '' => null,
            'si', 's', 'yes', 'y', 'true', '1' => true,
            'no', 'n', 'false', '0' => false,
            default => throw new FilaRechazada("Respuesta no reconocida: «{$valor}». Usa Sí o No.", $columna),
        };
    }

    /** Para comparar nombres (empleadores): sin tildes, mayúsculas ni espacios sobrantes. */
    public static function nombreNormalizado(string $nombre): string
    {
        $sinAcentos = self::sinAcentos(mb_strtoupper(trim($nombre)));

        return preg_replace('/\s+/', ' ', $sinAcentos) ?? '';
    }

    private static function sinAcentos(string $texto): string
    {
        return strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
        ]);
    }
}
