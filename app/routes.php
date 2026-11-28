<?php

declare(strict_types=1);

use App\Controllers\RoomController;
use Illuminate\Database\Capsule\Manager as Capsule;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

/*
 * Page URLs that players see or share (/sala/ABCD, /rivales, /solo) stay in
 * Spanish. Everything else (form targets and the JSON API) is in English.
 */
return function (App $app): void {

    // --- Pages ---------------------------------------------------------------
    $app->get('/', [RoomController::class, 'home']);
    $app->get('/solo', [RoomController::class, 'soloForm']);
    $app->post('/solo', [RoomController::class, 'createSolo']);
    $app->get('/rivales', [RoomController::class, 'multiplayer']);
    $app->post('/rooms', [RoomController::class, 'create']);
    $app->post('/join', [RoomController::class, 'join']);
    $app->get('/sala/{code}', [RoomController::class, 'room']);
    $app->post('/rooms/{code}/leave', [RoomController::class, 'leave']);

    // --- API used by the room JavaScript (JSON) -----------------------------
    $app->get('/api/rooms/{code}', [RoomController::class, 'state']);
    $app->post('/api/rooms/{code}/messages', [RoomController::class, 'sendMessage']);
    $app->post('/api/rooms/{code}/start', [RoomController::class, 'start']);
    $app->post('/api/rooms/{code}/answer', [RoomController::class, 'answer']);
    $app->post('/api/rooms/{code}/lobby', [RoomController::class, 'backToLobby']);

    // Quick check that PHP and SQLite work.
    // Open /salud in the browser: it must say "journal_mode": "wal".
    $health = function (Request $request, Response $response): Response {
        $db = Capsule::connection();
        $data = [
            'ok'           => true,
            'php'          => PHP_VERSION,
            'sqlite'       => $db->selectOne('select sqlite_version() as v')->v,
            'journal_mode' => $db->selectOne('PRAGMA journal_mode')->journal_mode,
            'foreign_keys' => (int) $db->selectOne('PRAGMA foreign_keys')->foreign_keys,
            'schema'       => (int) $db->selectOne('PRAGMA user_version')->user_version,
            'questions'    => $db->table('questions')->count(),
        ];
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json');
    };
    $app->get('/salud', $health);
    $app->get('/health', $health);
};
