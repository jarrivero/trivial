<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int     $id
 * @property int     $room_id
 * @property string  $name
 * @property int     $score
 * @property ?string $last_seen_at
 */
final class Player extends Model
{
    protected $table = 'players';
    protected $guarded = [];
    protected $casts = ['room_id' => 'integer', 'score' => 'integer'];

    /** Seconds without polling the server before a player counts as offline. */
    public const ONLINE_SECONDS = 15;

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && strtotime((string) $this->last_seen_at) >= time() - self::ONLINE_SECONDS;
    }

    /**
     * Records that the player is still here. To avoid a database write on
     * every poll, it only updates if more than 5 s have passed.
     */
    public function touchLastSeen(): void
    {
        if ($this->last_seen_at === null || strtotime((string) $this->last_seen_at) < time() - 5) {
            $this->last_seen_at = date('Y-m-d H:i:s');
            $this->timestamps = false;   // do not bump updated_at for this
            $this->save();
            $this->timestamps = true;
        }
    }
}
