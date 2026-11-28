<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int    $id
 * @property string $codigo
 * @property string $estado
 * @property ?int   $creador_id
 */
final class Sala extends Model
{
    protected $table = 'salas';
    protected $guarded = [];
    protected $casts = ['creador_id' => 'integer'];

    /** Máximo de jugadores por sala. */
    public const MAX_JUGADORES = 12;

    /** Letras del código: sin I, L, O ni Ñ para que no se confundan al dictarlo. */
    private const LETRAS = 'ABCDEFGHJKMNPQRSTUVWXYZ';
    private const LARGO_CODIGO = 4;

    public function jugadores(): HasMany
    {
        return $this->hasMany(Jugador::class)->orderBy('id');
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(Mensaje::class);
    }

    public static function porCodigo(string $codigo): ?self
    {
        $codigo = strtoupper(trim($codigo));
        return $codigo === '' ? null : self::where('codigo', $codigo)->first();
    }

    /** Genera un código de 4 letras que no esté en uso (≈280.000 combinaciones). */
    public static function nuevoCodigo(): string
    {
        do {
            $codigo = '';
            for ($i = 0; $i < self::LARGO_CODIGO; $i++) {
                $codigo .= self::LETRAS[random_int(0, strlen(self::LETRAS) - 1)];
            }
        } while (self::where('codigo', $codigo)->exists());

        return $codigo;
    }

    /**
     * Borra salas abandonadas: sin cambios y sin nadie conectado en las últimas 24 h.
     * Sus jugadores y mensajes se borran con ellas (ON DELETE CASCADE).
     */
    public static function limpiarAntiguas(): void
    {
        $limite = date('Y-m-d H:i:s', time() - 86400);
        self::where('updated_at', '<', $limite)
            ->whereDoesntHave('jugadores', fn ($q) => $q->where('visto_en', '>=', $limite))
            ->delete();
    }
}
