<?php

declare(strict_types=1);

namespace App;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Loads the questions from the questions/ folder (.json files) into the database.
 *
 * Safe to run as often as you like: questions already present (same text)
 * are skipped. So after adding questions to a file, restarting the
 * container is enough to load them.
 */
final class QuestionBank
{
    public const DIFFICULTIES = ['easy', 'medium', 'hard'];

    public static function folder(): string
    {
        return dirname(__DIR__) . '/questions';
    }

    public static function importIfEmpty(): void
    {
        if (!Capsule::table('questions')->exists()) {
            self::import();
        }
    }

    /**
     * @return array{added: int, skipped: int, errors: list<string>}
     */
    public static function import(): array
    {
        $result = ['added' => 0, 'skipped' => 0, 'errors' => []];
        $files = glob(self::folder() . '/*.json') ?: [];
        sort($files);

        $db = Capsule::connection();
        $known = array_flip($db->table('questions')->pluck('fingerprint')->all());

        foreach ($files as $file) {
            $name = basename($file);
            $list = json_decode((string) file_get_contents($file), true);
            if (!is_array($list)) {
                $result['errors'][] = "$name: not valid JSON";
                continue;
            }

            $db->transaction(function () use ($db, $list, $name, &$known, &$result) {
                foreach ($list as $i => $q) {
                    $error = self::validate($q);
                    if ($error !== null) {
                        $result['errors'][] = "$name, question " . ($i + 1) . ": $error";
                        continue;
                    }
                    $fingerprint = self::fingerprint($q['text']);
                    if (isset($known[$fingerprint])) {
                        $result['skipped']++;
                        continue;
                    }
                    $now = date('Y-m-d H:i:s');
                    $id = $db->table('questions')->insertGetId([
                        'text' => trim($q['text']),
                        'category' => trim((string) ($q['category'] ?? 'General')),
                        'difficulty' => $q['difficulty'],
                        'origin' => ($q['origin'] ?? 'general') === 'local' ? 'local' : 'general',
                        'source' => isset($q['source']) ? mb_substr((string) $q['source'], 0, 120) : null,
                        'fingerprint' => $fingerprint,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    foreach ($q['options'] as $option) {
                        $db->table('answer_options')->insert([
                            'question_id' => $id,
                            'text' => trim((string) $option['text']),
                            'is_correct' => !empty($option['is_correct']),
                        ]);
                    }
                    $known[$fingerprint] = true;
                    $result['added']++;
                }
            });
        }

        return $result;
    }

    private static function validate(mixed $q): ?string
    {
        if (!is_array($q) || trim((string) ($q['text'] ?? '')) === '') {
            return 'missing text';
        }
        if (!in_array($q['difficulty'] ?? null, self::DIFFICULTIES, true)) {
            return 'unknown difficulty (use easy, medium or hard)';
        }
        $options = $q['options'] ?? null;
        if (!is_array($options) || count($options) !== 4) {
            return 'must have exactly 4 options';
        }
        $correct = count(array_filter($options, fn ($o) => !empty($o['is_correct'])));
        if ($correct !== 1) {
            return 'must have exactly one correct option';
        }
        return null;
    }

    /** Same text (ignoring case and spacing) = same question. */
    private static function fingerprint(string $text): string
    {
        return sha1(mb_strtolower(preg_replace('/\s+/u', ' ', trim($text)) ?? ''));
    }
}
