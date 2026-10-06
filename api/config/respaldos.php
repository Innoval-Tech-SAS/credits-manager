<?php

return [
    /*
    | Carpeta del respaldo dentro del contenedor. Vuelve a ser el volumen `respaldos_data` o la
    | carpeta del equipo que indique RESPALDOS_ORIGEN en el compose.
    */
    'ruta' => env('RESPALDOS_RUTA', '/var/respaldos'),

    /*
    | Cliente que vuelca la base. Alpine no trae el de MySQL; el de MariaDB sirve para MySQL 8.4
    | (la restauración se prueba con el cliente de MySQL, ver docs/respaldos.md).
    */
    'binario_volcado' => env('RESPALDOS_BINARIO_VOLCADO', 'mariadb-dump'),

    /*
    | Tablas de estado técnico que no forman parte del respaldo: se regeneran solas al arrancar.
    */
    'tablas_excluidas' => ['sessions', 'cache', 'cache_locks'],

    /*
    | Columnas que nunca salen con valor en las descargas: hashes de contraseña y tokens de recordar.
    */
    'columnas_omitidas' => ['password_hash', 'remember_token'],

    /*
    | Catálogos sin empresa que la descarga de empresa sí incluye completos. Cualquier otra tabla sin
    | camino a una empresa se omite: un dato de empresa nunca sale por error de esta lista.
    */
    'tablas_globales' => ['estados', 'migrations', 'municipios', 'paises', 'permisos', 'valores_uvt'],

    /*
    | Directorios de archivos, sin depender de la base. Cada uno entra al tar con su nombre base
    | (`app/`, `exportaciones/`, `importaciones/`). Los mismos volúmenes que monta el scheduler.
    */
    'directorios' => [
        storage_path('app'),
        env('EXPORTACIONES_PATH', '/var/exportaciones'),
        env('IMPORTACIONES_PATH', '/var/importaciones'),
    ],
];
