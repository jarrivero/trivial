<?php

declare(strict_types=1);

/*
 * Settings in one place. Paths are absolute so it works the same in
 * Docker, locally or on shared hosting.
 */

$root = dirname(__DIR__);

return [
    'debug' => filter_var(getenv('APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOLEAN),

    'db' => [
        'path' => getenv('DB_PATH') ?: $root . '/data/trivial.sqlite',
        // Milliseconds SQLite waits if another process is writing,
        // instead of failing at once with "database is locked".
        'busy_timeout' => 5000,
    ],

    'smarty' => [
        'templates' => $root . '/templates',
        'compile'   => $root . '/var/cache/smarty',
    ],

    'session' => [
        'name'     => 'trivial_sid',
        // 12 hours: enough for an afternoon of games and for reconnecting.
        'lifetime' => 60 * 60 * 12,
    ],
];
