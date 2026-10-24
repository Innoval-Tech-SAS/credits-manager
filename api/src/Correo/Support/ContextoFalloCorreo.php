<?php

namespace App\Correo\Support;

use App\Correo\Enums\CifradoCorreo;
use App\Correo\Models\ConfiguracionCorreo;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

/** Único lugar que arma el contexto de log de un fallo de envío. Nunca incluye usuario ni clave SMTP. */
final class ContextoFalloCorreo
{
    private const MAXIMO_CARACTERES_DEBUG = 4000;

    private const MAXIMO_EXCEPCIONES_PREVIAS = 5;

    public static function construir(?ConfiguracionCorreo $configuracion, Throwable $excepcion, float $duracionMs): array
    {
        return [
            'excepcion' => $excepcion::class,
            'mensaje' => self::sanitizar($excepcion->getMessage(), $configuracion),
            'codigo' => $excepcion->getCode(),
            'debug' => self::debug($excepcion, $configuracion),
            'previas' => self::previas($excepcion, $configuracion),
            'configuracion' => self::datosConfiguracion($configuracion),
            'duracion_ms' => round($duracionMs, 2),
        ];
    }

    private static function debug(Throwable $excepcion, ?ConfiguracionCorreo $configuracion): ?string
    {
        if (! $excepcion instanceof TransportExceptionInterface) {
            return null;
        }

        $debug = self::sanitizar($excepcion->getDebug(), $configuracion);

        return mb_strlen($debug) > self::MAXIMO_CARACTERES_DEBUG
            ? mb_substr($debug, -self::MAXIMO_CARACTERES_DEBUG)
            : $debug;
    }

    /** @return list<array{excepcion: string, mensaje: string}> */
    private static function previas(Throwable $excepcion, ?ConfiguracionCorreo $configuracion): array
    {
        $previas = [];
        $actual = $excepcion->getPrevious();

        while ($actual !== null && count($previas) < self::MAXIMO_EXCEPCIONES_PREVIAS) {
            $previas[] = ['excepcion' => $actual::class, 'mensaje' => self::sanitizar($actual->getMessage(), $configuracion)];
            $actual = $actual->getPrevious();
        }

        return $previas;
    }

    private static function datosConfiguracion(?ConfiguracionCorreo $configuracion): array
    {
        return [
            'host' => $configuracion?->host,
            'puerto' => $configuracion?->puerto,
            'cifrado' => $configuracion?->cifrado?->value,
            'scheme' => $configuracion?->cifrado === CifradoCorreo::Ssl ? 'smtps' : 'smtp',
            'remitente' => $configuracion?->remitente,
            'usuario_configurado' => filled($configuracion?->usuario),
        ];
    }

    /** Quita usuario y clave (texto plano y base64) y los payloads de AUTH PLAIN/LOGIN de la conversación SMTP. */
    private static function sanitizar(string $texto, ?ConfiguracionCorreo $configuracion): string
    {
        foreach (array_filter([$configuracion?->usuario, $configuracion?->clave]) as $secreto) {
            $texto = str_replace([$secreto, base64_encode($secreto)], '***', $texto);
        }

        return self::sanitizarAuth($texto);
    }

    private static function sanitizarAuth(string $texto): string
    {
        $lineas = preg_split('/\r\n|\r|\n/', $texto);
        $trasCodigo334 = false;

        foreach ($lineas as $indice => $linea) {
            if ($trasCodigo334) {
                $lineas[$indice] = preg_replace('/[A-Za-z0-9+\/]{4,}={0,2}/', '***', $linea);
                $trasCodigo334 = false;

                continue;
            }

            if (preg_match('/\b334\b/', $linea)) {
                $trasCodigo334 = true;
            }

            if (preg_match('/AUTH\s+(PLAIN|LOGIN)/i', $linea)) {
                $lineas[$indice] = preg_replace('/(AUTH\s+(?:PLAIN|LOGIN))\s*\S*/i', '$1 ***', $lineas[$indice]);
            }
        }

        return implode("\n", $lineas);
    }
}
