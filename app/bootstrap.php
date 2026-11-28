<?php

declare(strict_types=1);

use App\Database;
use App\View;
use DI\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Slim\Factory\AppFactory;

/*
 * Cableado de la aplicación: configuración, sesión, base de datos,
 * plantillas y Slim. Devuelve la $app lista para añadirle rutas.
 */

$settings = require __DIR__ . '/settings.php';

date_default_timezone_set('Europe/Madrid');

// --- Sesión -------------------------------------------------------------
// El jugador_id y su nombre vivirán SIEMPRE aquí (nunca se fían del navegador).
// Así, si alguien recarga la página, vuelve a la partida con sus puntos.
$isHttps = ($_SERVER['HTTPS'] ?? '') === 'on'
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

session_name($settings['session']['name']);
session_set_cookie_params([
    'lifetime' => $settings['session']['lifetime'],
    'path'     => '/',
    'secure'   => $isHttps,  // en el VPS con HTTPS la cookie solo viaja cifrada
    'httponly' => true,      // JavaScript no puede leerla
    'samesite' => 'Lax',
]);
ini_set('session.gc_maxlifetime', (string) $settings['session']['lifetime']);
ini_set('session.use_strict_mode', '1');
session_start();

// --- Contenedor de dependencias -----------------------------------------
$container = new Container();
$container->set('settings', $settings);
$container->set(Capsule::class, fn () => Database::boot($settings['db']));
$container->set(View::class, fn () => new View($settings['smarty'], $settings['debug']));

// Arrancamos la BD ya, para que los modelos Eloquent funcionen en cualquier ruta.
$container->get(Capsule::class);

// --- Slim ---------------------------------------------------------------
AppFactory::setContainer($container);
$app = AppFactory::create();

$app->addBodyParsingMiddleware();   // lee JSON y formularios en $request->getParsedBody()
$app->addRoutingMiddleware();
$app->addErrorMiddleware($settings['debug'], true, true);

return $app;
