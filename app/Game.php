<?php

declare(strict_types=1);

namespace App;

use App\Models\Player;
use App\Models\Room;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * The game rules. The SERVER owns the clock: the browser only shows what it
 * is told and reports what the player answers.
 *
 * A game goes through these phases (rooms.phase):
 *
 *   countdown (3 s, "Get ready!")
 *     → question 1 (20 s, or less if everyone has answered)
 *     → results 1 (6 s: correct answer and leaderboard)
 *     → question 2 → results 2 → … → results 10
 *     → room status = "finished" (final leaderboard)
 *
 * There is no background process: whenever someone polls for the state or
 * answers, we check whether it is time to move to the next phase.
 */
final class Game
{
    public const QUESTIONS = 10;
    public const COUNTDOWN_MS = 3000;
    public const QUESTION_MS = 20000;
    public const RESULTS_MS = 6000;
    /** Grace period for answers sent in time that arrive late over the network. */
    public const GRACE_MS = 800;

    public const MAX_POINTS = 1000;
    public const MIN_POINTS = 500;

    /** Difficulty value => label shown to the player. */
    public const DIFFICULTIES = [
        'easy' => 'Fácil',
        'medium' => 'Media',
        'hard' => 'Difícil',
        'mixed' => 'Mezcla',
    ];

    public static function nowMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    /** Points for a correct answer: 1000 if instant, 500 if answered at the last moment. */
    public static function points(int $elapsedMs): int
    {
        $elapsedMs = max(0, min(self::QUESTION_MS, $elapsedMs));
        return (int) round(self::MAX_POINTS - (self::MAX_POINTS - self::MIN_POINTS) * $elapsedMs / self::QUESTION_MS);
    }

    // ---------------------------------------------------------------------
    // Start
    // ---------------------------------------------------------------------

    /**
     * Picks 10 questions, resets scores and starts the countdown.
     * Returns an error message (in Spanish, shown to the player) or null.
     */
    public static function start(Room $room, string $difficulty): ?string
    {
        if (!isset(self::DIFFICULTIES[$difficulty])) {
            $difficulty = 'mixed';
        }

        return Capsule::connection()->transaction(function () use ($room, $difficulty) {
            $room->refresh();
            if ($room->status === 'playing') {
                return null;   // double click: already started
            }

            $gameNumber = $room->game_number + 1;
            $ids = self::pickQuestions($room, $difficulty);
            if ($ids === []) {
                return 'No hay preguntas cargadas. Revisa la carpeta questions/.';
            }

            foreach ($ids as $i => $id) {
                Capsule::table('game_questions')->insert([
                    'room_id' => $room->id,
                    'game_number' => $gameNumber,
                    'round' => $i + 1,
                    'question_id' => $id,
                ]);
            }
            Player::where('room_id', $room->id)->update(['score' => 0]);

            $now = self::nowMs();
            $room->fill([
                'status' => 'playing',
                'difficulty' => $difficulty,
                'game_number' => $gameNumber,
                'round' => 0,
                'phase' => 'countdown',
                'phase_started_ms' => $now,
                'phase_ends_ms' => $now + self::COUNTDOWN_MS,
            ])->save();

            return null;
        });
    }

    /**
     * 10 random questions of the chosen difficulty, skipping those already
     * played in this room. If there are not enough, fill with other
     * difficulties and, as a last resort, allow repeats.
     *
     * @return list<int>
     */
    private static function pickQuestions(Room $room, string $difficulty): array
    {
        $used = Capsule::table('game_questions')->where('room_id', $room->id)->pluck('question_id')->all();

        $attempts = [
            fn ($q) => $q->whereNotIn('id', $used)->when($difficulty !== 'mixed', fn ($q) => $q->where('difficulty', $difficulty)),
            fn ($q) => $q->whereNotIn('id', $used),
            fn ($q) => $q,
        ];

        $ids = [];
        foreach ($attempts as $filter) {
            $missing = self::QUESTIONS - count($ids);
            if ($missing <= 0) {
                break;
            }
            $query = Capsule::table('questions')->whereNotIn('id', $ids);
            $found = $filter($query)->inRandomOrder()->limit($missing)->pluck('id')->all();
            $ids = array_merge($ids, array_map('intval', $found));
        }
        return $ids;
    }

    // ---------------------------------------------------------------------
    // Moving forward in time
    // ---------------------------------------------------------------------

    /**
     * Moves to the next phase if it is due (time is up or everyone answered).
     * If nobody looked for a while, it may take several steps at once.
     */
    public static function advance(Room $room): void
    {
        for ($i = 0; $i < 3 * self::QUESTIONS + 2 && $room->status === 'playing'; $i++) {
            $now = self::nowMs();
            $timeUp = $now >= (int) $room->phase_ends_ms;
            $allAnswered = $room->phase === 'question' && self::allOnlinePlayersAnswered($room);
            if (!$timeUp && !$allAnswered) {
                return;
            }

            // The new phase starts when the previous one ended (or now, if it ended early).
            $start = min($now, (int) $room->phase_ends_ms);
            $changes = match ($room->phase) {
                'countdown' => ['phase' => 'question', 'round' => 1, 'phase_started_ms' => $start, 'phase_ends_ms' => $start + self::QUESTION_MS],
                'question' => ['phase' => 'results', 'phase_started_ms' => $start, 'phase_ends_ms' => $start + self::RESULTS_MS],
                default => $room->round >= self::QUESTIONS
                    ? ['status' => 'finished', 'phase' => null, 'phase_started_ms' => $start, 'phase_ends_ms' => null]
                    : ['phase' => 'question', 'round' => $room->round + 1, 'phase_started_ms' => $start, 'phase_ends_ms' => $start + self::QUESTION_MS],
            };

            // Several players may get here at the same time. Only the first one
            // makes the change: the others no longer find the row as it was.
            Capsule::table('rooms')
                ->where('id', $room->id)
                ->where('status', 'playing')
                ->where('game_number', $room->game_number)
                ->where('round', $room->round)
                ->where('phase', $room->phase)
                ->update($changes + ['updated_at' => date('Y-m-d H:i:s')]);

            $room->refresh();
        }
    }

    private static function allOnlinePlayersAnswered(Room $room): bool
    {
        $answered = self::currentAnswers($room)->keys()->all();
        if ($answered === []) {
            return false;
        }
        $answered = array_flip($answered);

        foreach ($room->players()->get() as $player) {
            if ($player->isOnline() && !isset($answered[$player->id])) {
                return false;
            }
        }
        return true;
    }

    // ---------------------------------------------------------------------
    // Answering
    // ---------------------------------------------------------------------

    /**
     * Stores the player's answer. Only one per question, and only while the
     * question is open. Points are computed with the SERVER clock, so nobody
     * can cheat from the browser.
     *
     * Returns an error message (in Spanish, shown to the player) or null.
     */
    public static function answer(Room $room, Player $me, int $round, int $optionId): ?string
    {
        $now = self::nowMs();
        self::advance($room);

        // If the question has just closed but the answer was sent in time, accept it.
        $open = $room->status === 'playing' && $room->round === $round && (
            $room->phase === 'question'
            || ($room->phase === 'results' && $now <= (int) $room->phase_started_ms + self::GRACE_MS)
        );
        if (!$open) {
            return 'Se acabó el tiempo para esta pregunta.';
        }

        $question = self::questionForRound($room, $round);
        $option = $question === null ? null : Capsule::table('answer_options')
            ->where('id', $optionId)->where('question_id', $question->id)->first();
        if ($option === null) {
            return 'Esa opción no es de esta pregunta.';
        }

        // An answer that arrives during the grace period counts as the last instant.
        $elapsed = $room->phase === 'question' ? $now - (int) $room->phase_started_ms : self::QUESTION_MS;
        $isCorrect = (bool) $option->is_correct;
        $points = $isCorrect ? self::points($elapsed) : 0;

        $saved = Capsule::connection()->transaction(function () use ($room, $me, $round, $optionId, $isCorrect, $points, $elapsed) {
            $already = Capsule::table('answers')->where([
                'room_id' => $room->id, 'game_number' => $room->game_number, 'round' => $round, 'player_id' => $me->id,
            ])->exists();
            if ($already) {
                return false;
            }
            Capsule::table('answers')->insert([
                'room_id' => $room->id,
                'player_id' => $me->id,
                'game_number' => $room->game_number,
                'round' => $round,
                'option_id' => $optionId,
                'is_correct' => $isCorrect,
                'points' => $points,
                'elapsed_ms' => max(0, $elapsed),
            ]);
            if ($points > 0) {
                Player::where('id', $me->id)->increment('score', $points);
            }
            return true;
        });
        if (!$saved) {
            return 'Ya habías contestado esta pregunta.';
        }

        self::advance($room);   // if this was the last player to answer, show results now
        return null;
    }

    // ---------------------------------------------------------------------
    // What each player sees (added to the polling JSON)
    // ---------------------------------------------------------------------

    public static function stateFor(Room $room, Player $me): array
    {
        $now = self::nowMs();
        $inRound = $room->status === 'playing' && in_array($room->phase, ['question', 'results'], true);
        $showSolution = $room->phase === 'results';
        $answers = $inRound ? self::currentAnswers($room) : collect();

        $state = [
            'gameNumber' => $room->game_number,
            'round' => $room->round,
            'totalRounds' => self::QUESTIONS,
            'phase' => $room->status === 'finished' ? 'finished' : $room->phase,
            'difficulty' => self::DIFFICULTIES[$room->difficulty] ?? self::DIFFICULTIES['mixed'],
            'remainingMs' => $room->phase_ends_ms === null ? null : max(0, $room->phase_ends_ms - $now),
            'durationMs' => $room->phase_ends_ms === null ? null : $room->phase_ends_ms - (int) $room->phase_started_ms,
            'question' => null,
            'myAnswer' => null,
            'answeredCount' => $answers->count(),
        ];

        if ($inRound && ($question = self::questionForRound($room, $room->round)) !== null) {
            $state['question'] = [
                'text' => $question->text,
                'category' => $question->category,
                'options' => self::shuffledOptions($room, (int) $question->id, $showSolution),
            ];
        }

        if (($mine = $answers->get($me->id)) !== null) {
            $state['myAnswer'] = ['optionId' => (int) $mine->option_id] + ($showSolution
                ? ['isCorrect' => (bool) $mine->is_correct, 'points' => (int) $mine->points]
                : []);
        }

        // Leaderboard: highest score first. During results, also the points won in this round.
        $state['leaderboard'] = $room->players()->get()
            ->sortBy([['score', 'desc'], ['id', 'asc']])
            ->values()
            ->map(fn (Player $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'score' => $p->score,
                'isMe' => $p->id === $me->id,
                'hasAnswered' => $answers->has($p->id),
                'roundPoints' => $showSolution && $answers->has($p->id) ? (int) $answers->get($p->id)->points : null,
            ])->all();

        return $state;
    }

    /** Answers to the current round, keyed by player id. */
    private static function currentAnswers(Room $room): \Illuminate\Support\Collection
    {
        return Capsule::table('answers')
            ->where('room_id', $room->id)
            ->where('game_number', $room->game_number)
            ->where('round', $room->round)
            ->get()
            ->keyBy(fn ($a) => (int) $a->player_id);
    }

    private static function questionForRound(Room $room, int $round): ?object
    {
        return Capsule::table('game_questions as gq')
            ->join('questions as q', 'q.id', '=', 'gq.question_id')
            ->where('gq.room_id', $room->id)
            ->where('gq.game_number', $room->game_number)
            ->where('gq.round', $round)
            ->select('q.*')
            ->first();
    }

    /**
     * The 4 options, in a different order each game (but the same for every
     * player and every poll). Which one is correct is only revealed at the end.
     */
    private static function shuffledOptions(Room $room, int $questionId, bool $withSolution): array
    {
        return Capsule::table('answer_options')->where('question_id', $questionId)->get()
            ->sortBy(fn ($o) => crc32($room->id . '-' . $room->game_number . '-' . $o->id))
            ->values()
            ->map(fn ($o) => ['id' => (int) $o->id, 'text' => $o->text]
                + ($withSolution ? ['isCorrect' => (bool) $o->is_correct] : []))
            ->all();
    }
}
