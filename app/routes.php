<?php

declare(strict_types=1);

use App\View;
use Illuminate\Database\Capsule\Manager as Capsule;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app): void {

    // Página de inicio (de momento vacía, solo para ver el layout).
    $app->get('/', function (Request $request, Response $response): Response {
        return $this->get(View::class)->render($response, 'home.tpl', [
            'titulo' => 'Trivial',
        ]);
    });

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
        ];
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json');
    });
};
