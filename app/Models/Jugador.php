<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int    $id
 * @property int    $sala_id
 * @property string $nombre
 * @property int    $puntos
 * @property ?string $visto_en
 */
final class Jugador extends Model
{
    protected $table = 'jugadores';
    protected $guarded = [];
    protected $casts = ['sala_id' => 'integer', 'puntos' => 'integer'];

    /** Segundos sin preguntar al servidor para considerarlo "desconectado". */
    public const SEGUNDOS_CONECTADO = 15;

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class);
    }

    public function conectado(): bool
    {
        return $this->visto_en !== null
            && strtotime((string) $this->visto_en) >= time() - self::SEGUNDOS_CONECTADO;
    }

    /**
     * Apunta que el jugador sigue ahí. Para no escribir en la base de datos
     * en cada consulta (cada 2 s), solo actualiza si han pasado más de 5 s.
     */
    public function marcarVisto(): void
    {
        if ($this->visto_en === null || strtotime((string) $this->visto_en) < time() - 5) {
            $this->visto_en = date('Y-m-d H:i:s');
            $this->timestamps = false;   // no tocar updated_at por esto
            $this->save();
            $this->timestamps = true;
        }
    }
}
