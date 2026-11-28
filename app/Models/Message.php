<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A chat message in a room.
 *
 * @property int    $id
 * @property int    $room_id
 * @property ?int   $player_id
 * @property string $name
 * @property string $body
 */
final class Message extends Model
{
    protected $table = 'messages';
    protected $guarded = [];
    protected $casts = ['player_id' => 'integer'];

    public const UPDATED_AT = null;   // messages are never edited
    public const MAX_LENGTH = 200;
}
