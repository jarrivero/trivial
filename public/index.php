<?php

declare(strict_types=1);

/*
 * Punto de entrada único: Nginx manda aquí todas las peticiones.
 * Aquí solo arrancamos la app; el cableado está en app/bootstrap.php
 * y las rutas en app/routes.php.
 */

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!is_file($autoload)) {
    // Sin librerías no podemos arrancar: mejor un aviso claro que un error 500 en blanco.
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
