<?php

declare(strict_types=1);

use App\Controllers\SalaController;
use Illuminate\Database\Capsule\Manager as Capsule;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app): void {

    // --- Páginas -----------------------------------------------------------
    $app->get('/', [SalaController::class, 'inicio']);
    $app->get('/rivales', [SalaController::class, 'rivales']);
    $app->post('/salas', [SalaController::class, 'crear']);
    $app->post('/unirse', [SalaController::class, 'unirse']);
    $app->get('/sala/{codigo}', [SalaController::class, 'lobby']);
    $app->post('/sala/{codigo}/salir', [SalaController::class, 'salir']);

    // --- API que usa el JavaScript del lobby (JSON) ------------------------
    $app->get('/api/sala/{codigo}', [SalaController::class, 'estado']);
    $app->post('/api/sala/{codigo}/mensajes', [SalaController::class, 'enviarMensaje']);
    $app->post('/api/sala/{codigo}/empezar', [SalaController::class, 'empezar']);

    // Comprobación rápida de que PHP y SQLite funcionan.
    // Abre /salud en el navegador: debe decir "journal_mode": "wal".
    $app->get('/salud', function (Request $request, Response $response): Response {
        $db = Capsule::connection();
        $data = [
            'ok'           => true,
            'php'          => PHP_VERSION,
            'sqlite'       => $db->selectOne('select sqlite_version() as v')->v,
            'journal_mode' => $db->selectOne('PRAGMA journal_mode')->journal_mode,
            'foreign_keys' => (int) $db->selectOne('PRAGMA foreign_keys')->foreign_keys,
            'esquema'      => (int) $db->selectOne('PRAGMA user_version')->user_version,
        ];
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json');
    });
};
