<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A room = one "with rivals" game, identified by a short code.
 * A solo game is also a room, with a single player and solo = true.
 *
 * @property int     $id
 * @property string  $code
 * @property string  $status            lobby | playing | finished
 * @property ?int    $host_id
 * @property bool    $solo
 * @property string  $difficulty        easy | medium | hard | mixed
 * @property int     $game_number       how many games this room has started
 * @property int     $round             current question (1..10)
 * @property ?string $phase             countdown | question | results
 * @property ?int    $phase_started_ms
 * @property ?int    $phase_ends_ms
 */
final class Room extends Model
{
    protected $table = 'rooms';
    protected $guarded = [];
    protected $casts = [
        'host_id' => 'integer',
        'solo' => 'boolean',
        'game_number' => 'integer',
        'round' => 'integer',
        'phase_started_ms' => 'integer',
        'phase_ends_ms' => 'integer',
    ];
    protected $attributes = ['solo' => false, 'difficulty' => 'mixed', 'game_number' => 0, 'round' => 0];

    public const MAX_PLAYERS = 12;

    /** Code letters: no I, L, O or Ñ so they are not confused when read aloud. */
    private const LETTERS = 'ABCDEFGHJKMNPQRSTUVWXYZ';
    private const CODE_LENGTH = 4;

    public function players(): HasMany
    {
        return $this->hasMany(Player::class)->orderBy('id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public static function findByCode(string $code): ?self
    {
        $code = strtoupper(trim($code));
        return $code === '' ? null : self::where('code', $code)->first();
    }

    /** Generates an unused 4-letter code (≈280,000 combinations). */
    public static function newCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::LETTERS[random_int(0, strlen(self::LETTERS) - 1)];
            }
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /**
     * Deletes abandoned rooms: no changes and nobody online in the last 24 h.
     * Their players, messages and answers go with them (ON DELETE CASCADE).
     */
    public static function deleteAbandoned(): void
    {
        $limit = date('Y-m-d H:i:s', time() - 86400);
        self::where('updated_at', '<', $limit)
            ->whereDoesntHave('players', fn ($q) => $q->where('last_seen_at', '>=', $limit))
            ->delete();
    }
}
