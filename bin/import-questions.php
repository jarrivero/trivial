<?php

declare(strict_types=1);

/*
 * Loads new questions from the questions/ folder into the database.
 * Questions already present are skipped, so it is always safe to run.
 *
 *   docker compose exec php php bin/import-questions.php
 *
 * (The container already runs it on every start.)
 */

use App\Database;
use App\QuestionBank;
use App\Schema;

require dirname(__DIR__) . '/vendor/autoload.php';

$settings = require dirname(__DIR__) . '/app/settings.php';
date_default_timezone_set('Europe/Madrid');

Database::boot($settings['db']);
Schema::migrate();

$result = QuestionBank::import();
$total = Illuminate\Database\Capsule\Manager::table('questions')->count();
printf("[trivial] Questions in the database: %d (%d new from questions/).\n", $total, $result['added']);
foreach ($result['errors'] as $error) {
    fwrite(STDERR, "[trivial] Warning: $error\n");
}
