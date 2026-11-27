<?php

declare(strict_types=1);

/*
 * Punto de entrada único: Nginx manda aquí todas las peticiones.
 * Aquí solo arrancamos la app; el cableado está en app/bootstrap.php
 * y las rutas en app/routes.php.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../app/bootstrap.php';

(require __DIR__ . '/../app/routes.php')($app);

$app->run();
