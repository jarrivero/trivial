<?php

declare(strict_types=1);

namespace App;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Arranca Eloquent contra un archivo SQLite y aplica los ajustes
 * necesarios para que varios jugadores escriban a la vez sin bloqueos.
 */
final class Database
{
    public static function boot(array $config): Capsule
    {
        $path = $config['path'];

        // SQLite crea el archivo, pero no la carpeta.
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        if (!file_exists($path)) {
            touch($path);
        }

        $capsule = new Capsule();
        $capsule->addConnection([
            'driver'                  => 'sqlite',
            'database'                => $path,
            'prefix'                  => '',
            'foreign_key_constraints' => true,
        ]);
        $capsule->setAsGlobal();   // permite usar Capsule::table(...) y Schema
        $capsule->bootEloquent();  // permite usar modelos Eloquent

        $pdo = $capsule->getConnection()->getPdo();

        // WAL: los lectores (polling cada 2-3 s) no bloquean al que escribe.
        // Se guarda en el propio archivo, pero repetirlo es inocuo.
        $pdo->exec('PRAGMA journal_mode = WAL');
        // Esperar en vez de fallar si la BD está ocupada un instante.
        $pdo->exec('PRAGMA busy_timeout = ' . (int) $config['busy_timeout']);
        // Las claves foráneas en SQLite van desactivadas por defecto.
        $pdo->exec('PRAGMA foreign_keys = ON');
        // Con WAL, NORMAL es seguro y bastante más rápido que FULL.
        $pdo->exec('PRAGMA synchronous = NORMAL');

        return $capsule;
    }
}
