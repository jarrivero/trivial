<?php

declare(strict_types=1);

namespace App;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

/**
 * Creates the tables if they do not exist yet.
 *
 * Called on every request, but it only costs one quick query
 * (PRAGMA user_version). When tables change in a future phase,
 * add a new step and bump VERSION.
 */
final class Schema
{
    private const VERSION = 2;

    public static function migrate(): void
    {
        $db = Capsule::connection();
        $current = (int) $db->selectOne('PRAGMA user_version')->user_version;
        if ($current >= self::VERSION) {
            return;
        }

        $schema = Capsule::schema();

        if ($current < 2) {
            $db->transaction(function () use ($schema, $db) {
                // Version 1 used Spanish table names. Rooms are short-lived, so
                // dropping them is harmless: at worst an open room disappears.
                foreach (['mensajes', 'jugadores', 'salas'] as $legacy) {
                    $schema->dropIfExists($legacy);
                }

                // --- Question bank (filled from the questions/ folder) ---
                $schema->create('questions', function (Blueprint $t) {
                    $t->id();
                    $t->string('text', 300);
                    $t->string('category', 40);
                    $t->string('difficulty', 10);                  // easy | medium | hard
                    $t->string('origin', 10)->default('general');  // general | local
                    $t->string('source', 120)->nullable();
                    // Fingerprint of the text: avoids importing the same question twice
                    $t->string('fingerprint', 40)->unique();
                    $t->timestamps();
                    $t->index('difficulty');
                });

                $schema->create('answer_options', function (Blueprint $t) {
                    $t->id();
                    $t->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
                    $t->string('text', 200);
                    $t->boolean('is_correct')->default(false);
                });

                // --- Rooms. A solo game is a room with a single player. ---
                $schema->create('rooms', function (Blueprint $t) {
                    $t->id();
                    $t->string('code', 8)->unique();
                    $t->string('status', 12)->default('lobby');    // lobby | playing | finished
                    $t->unsignedBigInteger('host_id')->nullable();
                    $t->boolean('solo')->default(false);
                    $t->string('difficulty', 10)->default('mixed'); // easy | medium | hard | mixed
                    $t->integer('game_number')->default(0);        // how many games this room has started
                    $t->integer('round')->default(0);              // current question (1..10)
                    $t->string('phase', 12)->nullable();           // countdown | question | results
                    $t->bigInteger('phase_started_ms')->nullable(); // server clock, milliseconds
                    $t->bigInteger('phase_ends_ms')->nullable();
                    $t->timestamps();
                });

                $schema->create('players', function (Blueprint $t) {
                    $t->id();
                    $t->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
                    $t->string('name', 20);
                    $t->integer('score')->default(0);
                    // Last time their browser asked for updates (to know who is still online)
                    $t->timestamp('last_seen_at')->nullable();
                    $t->timestamps();
                    $t->index(['room_id', 'name']);
                });

                $schema->create('messages', function (Blueprint $t) {
                    $t->id();
                    $t->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
                    $t->foreignId('player_id')->nullable()->constrained('players')->nullOnDelete();
                    // Keep the name in case the player leaves: the message still has an author.
                    $t->string('name', 20);
                    $t->string('body', 200);
                    $t->timestamp('created_at')->nullable();
                    $t->index(['room_id', 'id']);
                });

                // The 10 questions of each game, in order
                $schema->create('game_questions', function (Blueprint $t) {
                    $t->id();
                    $t->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
                    $t->integer('game_number');
                    $t->integer('round');
                    $t->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
                    $t->unique(['room_id', 'game_number', 'round']);
                });

                // What each player answered to each question (only once)
                $schema->create('answers', function (Blueprint $t) {
                    $t->id();
                    $t->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
                    $t->foreignId('player_id')->constrained('players')->cascadeOnDelete();
                    $t->integer('game_number');
                    $t->integer('round');
                    $t->unsignedBigInteger('option_id');
                    $t->boolean('is_correct');
                    $t->integer('points');
                    $t->integer('elapsed_ms');   // how long they took to answer
                    $t->unique(['room_id', 'game_number', 'round', 'player_id']);
                });

                $db->statement('PRAGMA user_version = 2');
            });
        }

        // First run (or if someone emptied the table): load the questions.
        QuestionBank::importIfEmpty();
    }
}
