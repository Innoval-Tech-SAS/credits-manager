<?php

return [
    /*
    | Caché donde los servicios dejan su latido. Redis: no escribe en MySQL cada pocos segundos y
    | sigue respondiendo aunque la base esté caída (los tests usan `array`).
    */
    'almacen' => env('MONITOREO_ALMACEN', 'redis'),

    /*
    | Dentro de un contenedor de Podman sin lxcfs, /proc muestra la CPU, la memoria y la carga del
    | servidor completo, no las del contenedor.
    */
    'ruta_proc' => env('MONITOREO_RUTA_PROC', '/proc'),

    /*
    | Servicios que no dejan latido: se comprueba que acepten conexión TCP en la red del compose.
    */
    'servicios_tcp' => [
        'api' => ['host' => env('MONITOREO_HOST_API', 'api'), 'puerto' => 9000],
        'nginx' => ['host' => env('MONITOREO_HOST_NGINX', 'nginx'), 'puerto' => 80],
        'reverb' => ['host' => env('REVERB_HOST', 'reverb'), 'puerto' => (int) env('REVERB_PORT', 8080)],
        'phpmyadmin' => ['host' => env('MONITOREO_HOST_PHPMYADMIN', 'phpmyadmin'), 'puerto' => 80],
    ],
];
