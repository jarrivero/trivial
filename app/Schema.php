<?php

declare(strict_types=1);

namespace App;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

/**
 * Crea las tablas si todavía no existen.
 *
 * Se llama en cada petición, pero solo cuesta una consulta rápida
 * (PRAGMA user_version). Cuando haya cambios de tablas en fases
 * futuras, se añade un "paso" nuevo y se sube VERSION.
 */
final class Schema
{
    private const VERSION = 1;

    public static function migrate(): void
    {
        $db = Capsule::connection();
        $actual = (int) $db->selectOne('PRAGMA user_version')->user_version;
        if ($actual >= self::VERSION) {
            return;
        }

        $schema = Capsule::schema();

        if ($actual < 1) {
            $db->transaction(function () use ($schema, $db) {
                // Una sala = una partida "con rivales". Se identifica por un código corto.
                $schema->create('salas', function (Blueprint $t) {
                    $t->id();
                    $t->string('codigo', 8)->unique();
                    // lobby → empezada (en Fase 3 vendrán los estados del juego)
                    $t->string('estado', 20)->default('lobby');
                    $t->unsignedBigInteger('creador_id')->nullable();
                    $t->timestamps();
                });

                $schema->create('jugadores', function (Blueprint $t) {
                    $t->id();
                    $t->foreignId('sala_id')->constrained('salas')->cascadeOnDelete();
                    $t->string('nombre', 20);
                    $t->integer('puntos')->default(0);
                    // Última vez que su navegador preguntó por novedades (para ver quién sigue conectado)
                    $t->timestamp('visto_en')->nullable();
                    $t->timestamps();
                    $t->index(['sala_id', 'nombre']);
                });

                $schema->create('mensajes', function (Blueprint $t) {
                    $t->id();
                    $t->foreignId('sala_id')->constrained('salas')->cascadeOnDelete();
                    $t->foreignId('jugador_id')->nullable()->constrained('jugadores')->nullOnDelete();
                    // Guardamos el nombre por si el jugador se va: el mensaje sigue teniendo autor.
                    $t->string('nombre', 20);
                    $t->string('texto', 200);
                    $t->timestamp('created_at')->nullable();
                    $t->index(['sala_id', 'id']);
                });

                $db->statement('PRAGMA user_version = 1');
            });
        }
    }
}
