<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Jugador;
use App\Models\Mensaje;
use App\Models\Sala;
use App\View;
use Illuminate\Database\Capsule\Manager as Capsule;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Modo "Con rivales": crear sala, unirse con código, lobby y chat.
 *
 * Quién es cada jugador lo sabe SOLO el servidor, por la sesión
 * ($_SESSION['jugador_id']). El navegador nunca dice "soy el jugador 5".
 * Por eso, al recargar la página, el jugador vuelve a su sala.
 */
final class SalaController
{
    public function __construct(private View $view)
    {
    }

    // ---------------------------------------------------------------------
    // Páginas
    // ---------------------------------------------------------------------

    /** Inicio: menú principal. Si estás en una sala, ofrece volver a ella. */
    public function inicio(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'home.tpl', [
            'titulo' => 'Trivial',
            'jugador' => $this->jugadorActual(),
        ]);
    }

    /** Formularios de "Crear sala" y "Unirme a una sala". */
    public function rivales(Request $request, Response $response): Response
    {
        $codigo = strtoupper(trim((string) ($request->getQueryParams()['codigo'] ?? '')));
        return $this->pintarRivales($response, ['codigo' => $codigo]);
    }

    public function crear(Request $request, Response $response): Response
    {
        $datos = (array) $request->getParsedBody();
        $nombre = self::limpiarNombre((string) ($datos['nombre'] ?? ''));

        if ($error = self::errorNombre($nombre)) {
            return $this->pintarRivales($response, ['error_crear' => $error, 'nombre' => $nombre], 422);
        }

        Sala::limpiarAntiguas();
        $this->salirDeLaSalaActual();

        $sala = Capsule::connection()->transaction(function () use ($nombre) {
            $sala = Sala::create(['codigo' => Sala::nuevoCodigo(), 'estado' => 'lobby']);
            $jugador = $sala->jugadores()->create(['nombre' => $nombre, 'visto_en' => date('Y-m-d H:i:s')]);
            $sala->creador_id = $jugador->id;
            $sala->save();
            $this->entrarComo($jugador);
            return $sala;
        });

        return self::redirigir($response, '/sala/' . $sala->codigo);
    }

    public function unirse(Request $request, Response $response): Response
    {
        $datos = (array) $request->getParsedBody();
        $codigo = strtoupper(trim((string) ($datos['codigo'] ?? '')));
        $nombre = self::limpiarNombre((string) ($datos['nombre'] ?? ''));
        $volver = ['codigo' => $codigo, 'nombre' => $nombre];

        $sala = Sala::porCodigo($codigo);
        if ($sala === null) {
            return $this->pintarRivales($response, $volver + ['error_unirse' => 'No existe ninguna sala con ese código. Revisa las letras.'], 422);
        }

        // ¿Ya estoy dentro de esa sala? Pues para dentro, sin repetir nada.
        $yo = $this->jugadorActual();
        if ($yo !== null && $yo->sala_id === $sala->id) {
            return self::redirigir($response, '/sala/' . $sala->codigo);
        }

        if ($error = self::errorNombre($nombre)) {
            return $this->pintarRivales($response, $volver + ['error_unirse' => $error], 422);
        }

        $error = Capsule::connection()->transaction(function () use ($sala, $nombre) {
            if ($sala->estado !== 'lobby') {
                return 'Esa partida ya ha empezado. Pide que creen otra sala.';
            }
            if ($sala->jugadores()->count() >= Sala::MAX_JUGADORES) {
                return 'La sala está llena (máximo ' . Sala::MAX_JUGADORES . ' jugadores).';
            }
            $repetido = $sala->jugadores()->whereRaw('lower(nombre) = ?', [mb_strtolower($nombre)])->exists();
            if ($repetido) {
                return 'Ya hay alguien llamado «' . $nombre . '» en la sala. Elige otro nombre.';
            }

            $this->salirDeLaSalaActual();
            $jugador = $sala->jugadores()->create(['nombre' => $nombre, 'visto_en' => date('Y-m-d H:i:s')]);
            $sala->touch();
            $this->entrarComo($jugador);
            return null;
        });

        if ($error !== null) {
            return $this->pintarRivales($response, $volver + ['error_unirse' => $error], 422);
        }

        return self::redirigir($response, '/sala/' . $sala->codigo);
    }

    /** El lobby. Si no eres de esta sala, te manda al formulario para unirte (con el código ya puesto). */
    public function lobby(Request $request, Response $response, array $args): Response
    {
        $sala = Sala::porCodigo($args['codigo']);
        if ($sala === null) {
            return $this->pintarRivales($response, ['error_unirse' => 'Esa sala ya no existe.'], 404);
        }
        if ($sala->codigo !== $args['codigo']) {
            return self::redirigir($response, '/sala/' . $sala->codigo);   // /sala/abcd → /sala/ABCD
        }

        $yo = $this->jugadorActual();
        if ($yo === null || $yo->sala_id !== $sala->id) {
            return self::redirigir($response, '/rivales?codigo=' . $sala->codigo);
        }

        $base = (string) $request->getUri()->withPath('')->withQuery('')->withFragment('');
        // Detrás de Caddy/HTTPS la URI llega como http://: corregimos el esquema para el enlace de invitación.
        if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
            $base = preg_replace('#^http://#', 'https://', $base);
        }

        return $this->view->render($response, 'lobby.tpl', [
            'titulo' => 'Sala ' . $sala->codigo,
            'sala' => $sala,
            'yo' => $yo,
            'enlace' => $base . '/sala/' . $sala->codigo,
        ]);
    }

    public function salir(Request $request, Response $response, array $args): Response
    {
        $this->salirDeLaSalaActual();
        return self::redirigir($response, '/');
    }

    // ---------------------------------------------------------------------
    // API para el JavaScript del lobby (responde JSON)
    // ---------------------------------------------------------------------

    /**
     * El navegador pregunta cada 2 s: "¿qué hay de nuevo?".
     * ?desde=N → solo los mensajes de chat con id mayor que N.
     */
    public function estado(Request $request, Response $response, array $args): Response
    {
        [$sala, $yo, $error] = $this->salaYJugadorParaApi($response, $args['codigo']);
        if ($error) {
            return $error;
        }

        $yo->marcarVisto();

        $desde = max(0, (int) ($request->getQueryParams()['desde'] ?? 0));
        $mensajes = $sala->mensajes()
            ->where('id', '>', $desde)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        return self::json($response, [
            'codigo' => $sala->codigo,
            'estado' => $sala->estado,
            'yo' => $yo->id,
            'soyCreador' => $sala->creador_id === $yo->id,
            'maxJugadores' => Sala::MAX_JUGADORES,
            'jugadores' => $sala->jugadores()->get()->map(fn (Jugador $j) => [
                'id' => $j->id,
                'nombre' => $j->nombre,
                'creador' => $j->id === $sala->creador_id,
                'conectado' => $j->conectado(),
            ])->all(),
            'mensajes' => $mensajes->map(fn (Mensaje $m) => [
                'id' => $m->id,
                'nombre' => $m->nombre,
                'texto' => $m->texto,
                'hora' => date('H:i', strtotime((string) $m->created_at)),
                'mio' => $m->jugador_id === $yo->id,
            ])->all(),
        ]);
    }

    public function enviarMensaje(Request $request, Response $response, array $args): Response
    {
        [$sala, $yo, $error] = $this->salaYJugadorParaApi($response, $args['codigo']);
        if ($error) {
            return $error;
        }

        $datos = (array) $request->getParsedBody();
        $texto = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) ($datos['texto'] ?? '')) ?? '');
        if ($texto === '') {
            return self::json($response, ['error' => 'El mensaje está vacío.'], 422);
        }
        $texto = mb_substr($texto, 0, Mensaje::MAX_LARGO);

        // Freno anti-spam: como mucho un mensaje por segundo por jugador.
        $ultimo = Mensaje::where('jugador_id', $yo->id)->orderByDesc('id')->value('created_at');
        if ($ultimo !== null && strtotime((string) $ultimo) >= time() - 1) {
            return self::json($response, ['error' => 'Más despacio 🙂'], 429);
        }

        $m = Mensaje::create([
            'sala_id' => $sala->id,
            'jugador_id' => $yo->id,
            'nombre' => $yo->nombre,
            'texto' => $texto,
        ]);
        $sala->touch();

        return self::json($response, ['ok' => true, 'id' => $m->id], 201);
    }

    /** Solo el creador puede empezar. En la Fase 3 esto arrancará la primera pregunta. */
    public function empezar(Request $request, Response $response, array $args): Response
    {
        [$sala, $yo, $error] = $this->salaYJugadorParaApi($response, $args['codigo']);
        if ($error) {
            return $error;
        }
        if ($sala->creador_id !== $yo->id) {
            return self::json($response, ['error' => 'Solo quien creó la sala puede empezar.'], 403);
        }
        if ($sala->estado !== 'lobby') {
            return self::json($response, ['ok' => true]);   // ya estaba empezada: no pasa nada
        }

        $sala->estado = 'empezada';
        $sala->save();

        return self::json($response, ['ok' => true]);
    }

    // ---------------------------------------------------------------------
    // Ayudantes
    // ---------------------------------------------------------------------

    /** El jugador de esta sesión, o null. Si su sala se borró, limpia la sesión. */
    private function jugadorActual(): ?Jugador
    {
        $id = $_SESSION['jugador_id'] ?? null;
        if ($id === null) {
            return null;
        }
        $jugador = Jugador::with('sala')->find($id);
        if ($jugador === null && session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION['jugador_id']);
        }
        return $jugador;
    }

    private function entrarComo(Jugador $jugador): void
    {
        session_regenerate_id(true);   // sesión nueva al "identificarse": evita robos de sesión
        $_SESSION['jugador_id'] = $jugador->id;
        $_SESSION['nombre'] = $jugador->nombre;   // para rellenar el formulario la próxima vez
    }

    /**
     * Saca al jugador de su sala. Si era el creador, el mando pasa al siguiente
     * jugador (el que lleve más tiempo). Si la sala se queda vacía, se borra.
     */
    private function salirDeLaSalaActual(): void
    {
        $yo = $this->jugadorActual();
        unset($_SESSION['jugador_id']);
        if ($yo === null) {
            return;
        }

        Capsule::connection()->transaction(function () use ($yo) {
            $sala = $yo->sala;
            $yo->delete();

            $siguiente = $sala->jugadores()->first();
            if ($siguiente === null) {
                $sala->delete();
                return;
            }
            if ($sala->creador_id === $yo->id) {
                $sala->creador_id = $siguiente->id;
            }
            $sala->touch();
            $sala->save();
        });
    }

    /**
     * Para la API: busca sala y jugador y CIERRA la sesión enseguida.
     * Cerrarla es importante: PHP bloquea la sesión mientras está abierta, y
     * el polling y el chat del mismo jugador se harían esperar entre sí.
     *
     * @return array{0: ?Sala, 1: ?Jugador, 2: ?Response}
     */
    private function salaYJugadorParaApi(Response $response, string $codigo): array
    {
        $yo = $this->jugadorActual();
        session_write_close();

        $sala = Sala::porCodigo($codigo);
        if ($sala === null) {
            return [null, null, self::json($response, ['error' => 'sala_no_existe'], 404)];
        }
        if ($yo === null || $yo->sala_id !== $sala->id) {
            return [null, null, self::json($response, ['error' => 'no_estas_en_la_sala'], 403)];
        }
        return [$sala, $yo, null];
    }

    private function pintarRivales(Response $response, array $datos, int $status = 200): Response
    {
        $yo = $this->jugadorActual();
        $datos += [
            'titulo' => 'Jugar con rivales',
            'nombre' => $_SESSION['nombre'] ?? '',
            'codigo' => '',
            'jugador' => $yo,
        ];
        return $this->view->render($response, 'rivales.tpl', $datos)->withStatus($status);
    }

    /** Quita espacios de más y caracteres raros. */
    private static function limpiarNombre(string $nombre): string
    {
        $nombre = preg_replace('/[\x00-\x1F\x7F]+/u', '', $nombre) ?? '';
        $nombre = preg_replace('/\s+/u', ' ', $nombre) ?? '';
        return trim($nombre);
    }

    private static function errorNombre(string $nombre): ?string
    {
        if ($nombre === '') {
            return 'Escribe tu nombre.';
        }
        if (mb_strlen($nombre) > 20) {
            return 'El nombre es muy largo (máximo 20 letras).';
        }
        return null;
    }

    private static function redirigir(Response $response, string $url): Response
    {
        return $response->withHeader('Location', $url)->withStatus(303);
    }

    private static function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store')
            ->withStatus($status);
    }
}
