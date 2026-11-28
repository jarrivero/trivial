<?php

declare(strict_types=1);

namespace App;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Boots Eloquent against a SQLite file and applies the settings needed
 * so several players can write at once without locking errors.
 */
final class Database
{
    public static function boot(array $config): Capsule
    {
        $path = $config['path'];

        // SQLite creates the file, but not the folder.
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
        $capsule->setAsGlobal();   // enables Capsule::table(...) and Schema
        $capsule->bootEloquent();  // enables Eloquent models

        $pdo = $capsule->getConnection()->getPdo();

        // WAL: readers (polling every 1-2 s) do not block the writer.
        // It is stored in the file itself, but repeating it is harmless.
        $pdo->exec('PRAGMA journal_mode = WAL');
        // Wait instead of failing if the DB is busy for a moment.
        $pdo->exec('PRAGMA busy_timeout = ' . (int) $config['busy_timeout']);
        // Foreign keys are disabled by default in SQLite.
        $pdo->exec('PRAGMA foreign_keys = ON');
        // With WAL, NORMAL is safe and much faster than FULL.
        $pdo->exec('PRAGMA synchronous = NORMAL');

        return $capsule;
    }
}
