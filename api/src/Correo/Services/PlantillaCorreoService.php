<?php

namespace App\Correo\Services;

use App\Clientes\Models\Cliente;
use App\Core\Dinero\Dinero;
use App\Correo\Constants\CorreoConstants;
use App\Creditos\Models\Cuota;
use Carbon\Carbon;

/**
 * Renderiza la plantilla de cobranza. El HTML lo escribe la empresa: las variables se escapan al sustituirlas
 * y el resultado pasa por `sanitizarHtml`. Es un filtro de etiquetas peligrosas, no un purificador completo;
 * la vista previa del cliente lo muestra en un iframe sin scripts.
 */
class PlantillaCorreoService
{
    /** @return list<string> variables que no existen en la plantilla */
    public static function variablesDesconocidas(string $texto): array
    {
        preg_match_all('/\{([a-z_]+)\}/', $texto, $coincidencias);

        return array_values(array_diff(array_unique($coincidencias[1]), array_keys(CorreoConstants::VARIABLES)));
    }

    /** Texto de asunto: sin HTML, en una línea. */
    public function renderizarAsunto(string $asunto, array $valores): string
    {
        return trim(str_replace(["\r", "\n"], ' ', strip_tags($this->sustituir($asunto, $valores, escapar: false))));
    }

    /** Cuerpo HTML: valores escapados y etiquetas peligrosas retiradas. */
    public function renderizarCuerpo(string $html, array $valores): string
    {
        return $this->sanitizarHtml($this->sustituir($this->sanitizarHtml($html), $valores, escapar: true));
    }

    public function sanitizarHtml(string $html): string
    {
        $etiquetasPeligrosas = 'script|iframe|object|embed|link|meta|form|base';

        $html = preg_replace("#<({$etiquetasPeligrosas})\\b[^>]*>.*?</\\1\\s*>#is", '', $html) ?? $html;
        $html = preg_replace("#<({$etiquetasPeligrosas})\\b[^>]*/?>#i", '', $html) ?? $html;
        $html = preg_replace('#\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html) ?? $html;

        return preg_replace('#(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2#i', '$1=$2#$2', $html) ?? $html;
    }

    /** @return array<string, string> */
    public function valoresDeCuota(string $nombreEmpresa, Cliente $cliente, Cuota $cuota): array
    {
        return [
            'cliente' => $cliente->tercero->nombre,
            'empresa' => $nombreEmpresa,
            'credito' => (string) $cuota->credito->codigo,
            'cuota' => (string) $cuota->numero_cuota,
            'valor' => $this->formatearPesos($cuota->valor),
            'fecha_vencimiento' => Carbon::parse($cuota->fecha_vencimiento)->format('d/m/Y'),
        ];
    }

    /** Pesos con separador de miles y dos decimales, en formato colombiano: $ 1.234.567,00. Sin float. */
    public function formatearPesos(Dinero $valor): string
    {
        $centavos = $valor->centavos();
        $absoluto = abs($centavos);

        return sprintf(
            '%s$ %s,%02d',
            $centavos < 0 ? '-' : '',
            number_format(intdiv($absoluto, 100), 0, ',', '.'),
            $absoluto % 100,
        );
    }

    /** @param array<string, string> $valores */
    private function sustituir(string $texto, array $valores, bool $escapar): string
    {
        $reemplazos = [];

        foreach (array_keys(CorreoConstants::VARIABLES) as $variable) {
            $valor = $valores[$variable] ?? '';
            $reemplazos['{'.$variable.'}'] = $escapar ? e($valor) : $valor;
        }

        return strtr($texto, $reemplazos);
    }
}
