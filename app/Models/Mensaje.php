<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Mensaje del chat del lobby.
 *
 * @property int    $id
 * @property int    $sala_id
 * @property ?int   $jugador_id
 * @property string $nombre
 * @property string $texto
 */
final class Mensaje extends Model
{
    protected $table = 'mensajes';
    protected $guarded = [];
    protected $casts = ['jugador_id' => 'integer'];

    public const UPDATED_AT = null;   // los mensajes no se editan
    public const MAX_LARGO = 200;
}
