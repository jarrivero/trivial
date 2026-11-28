<?php

declare(strict_types=1);

/*
 * Single entry point: Nginx sends every request here.
 * We only start the app; wiring is in app/bootstrap.php
 * and routes are in app/routes.php.
 */

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!is_file($autoload)) {
    // Without libraries we cannot start: a clear notice beats a blank 500 error.
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Faltan las librerías PHP (carpeta vendor/).\n"
       . "Ejecuta: docker compose exec php composer install\n";
    exit;
}
require $autoload;

$app = require __DIR__ . '/../app/bootstrap.php';

(require __DIR__ . '/../app/routes.php')($app);

$app->run();
