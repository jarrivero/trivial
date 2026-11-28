<?php

declare(strict_types=1);

use App\Database;
use App\Schema;
use App\View;
use DI\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Slim\Factory\AppFactory;

/*
 * Application wiring: settings, session, database, templates and Slim.
 * Returns the $app ready for routes to be added.
 */

$settings = require __DIR__ . '/settings.php';

date_default_timezone_set('Europe/Madrid');

// --- Session ------------------------------------------------------------
// The player_id and name ALWAYS live here (the browser is never trusted).
// So if someone reloads the page, they get back to the game with their score.
$isHttps = ($_SERVER['HTTPS'] ?? '') === 'on'
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

session_name($settings['session']['name']);
session_set_cookie_params([
    'lifetime' => $settings['session']['lifetime'],
    'path'     => '/',
    'secure'   => $isHttps,  // on the VPS with HTTPS the cookie only travels encrypted
    'httponly' => true,      // JavaScript cannot read it
    'samesite' => 'Lax',
]);
ini_set('session.gc_maxlifetime', (string) $settings['session']['lifetime']);
ini_set('session.use_strict_mode', '1');
session_start();

// --- Dependency container -----------------------------------------------
$container = new Container();
$container->set('settings', $settings);
$container->set(Capsule::class, fn () => Database::boot($settings['db']));
$container->set(View::class, fn () => new View($settings['smarty'], $settings['debug']));

// Boot the DB now so Eloquent models work in any route.
$container->get(Capsule::class);
// Creates the tables the first time (or upgrades them to a new version).
Schema::migrate();

// --- Slim ---------------------------------------------------------------
AppFactory::setContainer($container);
$app = AppFactory::create();

$app->addBodyParsingMiddleware();   // parses JSON and form bodies into $request->getParsedBody()
$app->addRoutingMiddleware();
$app->addErrorMiddleware($settings['debug'], true, true);

return $app;
