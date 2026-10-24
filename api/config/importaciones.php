<?php

return [
    /** Tamaño máximo del CSV subido, en megabytes. */
    'tamano_maximo_mb' => (int) env('IMPORTACION_TAMANO_MAXIMO_MB', 20),

    /** Filas por lote de lectura: cada lote precarga sus clientes, créditos y empleadores en una sola consulta. */
    'tamano_lote' => (int) env('IMPORTACION_TAMANO_LOTE', 500),
];
