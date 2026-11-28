<?php

declare(strict_types=1);

/*
 * Configuración en un solo sitio. Las rutas son absolutas para que
 * funcione igual en Docker, en local o en un hosting compartido.
 */

$root = dirname(__DIR__);

return [
    'debug' => filter_var(getenv('APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOLEAN),

    'db' => [
        'path' => getenv('DB_PATH') ?: $root . '/data/trivial.sqlite',
        // Milisegundos que SQLite espera si otro proceso está escribiendo,
        // en lugar de fallar al instante con "database is locked".
        'busy_timeout' => 5000,
    ],

    'smarty' => [
        'templates' => $root . '/templates',
        'compile'   => $root . '/var/cache/smarty',
    ],

    'session' => [
        'name'     => 'trivial_sid',
        // 12 horas: suficiente para una tarde de partidas y para reconectar.
        'lifetime' => 60 * 60 * 12,
    ],
];
