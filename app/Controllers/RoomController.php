<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Game;
use App\Models\Message;
use App\Models\Player;
use App\Models\Room;
use App\View;
use Illuminate\Database\Capsule\Manager as Capsule;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Rooms: create, join by code, lobby, chat and the game itself.
 * "Solo" mode is a room with a single player that starts right away.
 *
 * ONLY the server knows who each player is, through the session
 * ($_SESSION['player_id']). The browser never says "I am player 5".
 * That is why reloading the page brings the player back to their room.
 *
 * Texts shown to players are in Spanish; code is in English.
 */
final class RoomController
{
    public function __construct(private View $view)
    {
    }

    // ---------------------------------------------------------------------
    // Pages
    // ---------------------------------------------------------------------

    /** Home: main menu. If you are in a room, offers to go back to it. */
    public function home(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'home.tpl', [
            'title' => 'Trivial',
            'player' => $this->currentPlayer(),
        ]);
    }

    /** "Create room" and "Join a room" forms. */
    public function multiplayer(Request $request, Response $response): Response
    {
        $code = strtoupper(trim((string) ($request->getQueryParams()['codigo'] ?? '')));
        return $this->renderMultiplayer($response, ['code' => $code]);
    }

    /** "Play solo" form: name and difficulty. */
    public function soloForm(Request $request, Response $response): Response
    {
        return $this->renderSolo($response, []);
    }

    /** Creates a single-player room and starts the game straight away. */
    public function createSolo(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $name = self::cleanName((string) ($data['name'] ?? ''));
        $difficulty = (string) ($data['difficulty'] ?? 'mixed');

        if ($error = self::nameError($name)) {
            return $this->renderSolo($response, ['error' => $error, 'name' => $name, 'difficulty' => $difficulty], 422);
        }

        $room = $this->createRoomWithHost($name, solo: true);
        if ($error = Game::start($room, $difficulty)) {
            return $this->renderSolo($response, ['error' => $error, 'name' => $name], 500);
        }

        return self::redirect($response, '/sala/' . $room->code);
    }

    public function create(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $name = self::cleanName((string) ($data['name'] ?? ''));

        if ($error = self::nameError($name)) {
            return $this->renderMultiplayer($response, ['create_error' => $error, 'name' => $name], 422);
        }

        $room = $this->createRoomWithHost($name, solo: false);
        return self::redirect($response, '/sala/' . $room->code);
    }

    public function join(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        $name = self::cleanName((string) ($data['name'] ?? ''));
        $back = ['code' => $code, 'name' => $name];

        $room = Room::findByCode($code);
        if ($room === null) {
            return $this->renderMultiplayer($response, $back + ['join_error' => 'No existe ninguna sala con ese código. Revisa las letras.'], 422);
        }

        // Already in that room? Straight in, nothing to repeat.
        $me = $this->currentPlayer();
        if ($me !== null && $me->room_id === $room->id) {
            return self::redirect($response, '/sala/' . $room->code);
        }

        if ($error = self::nameError($name)) {
            return $this->renderMultiplayer($response, $back + ['join_error' => $error], 422);
        }

        $error = Capsule::connection()->transaction(function () use ($room, $name) {
            if ($room->solo) {
                return 'Esa es una partida en solitario: no se puede entrar.';
            }
            if ($room->status !== 'lobby') {
                return 'Esa partida ya ha empezado. Espera a que acabe o pide que creen otra sala.';
            }
            if ($room->players()->count() >= Room::MAX_PLAYERS) {
                return 'La sala está llena (máximo ' . Room::MAX_PLAYERS . ' jugadores).';
            }
            $taken = $room->players()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists();
            if ($taken) {
                return 'Ya hay alguien llamado «' . $name . '» en la sala. Elige otro nombre.';
            }

            $this->leaveCurrentRoom();
            $player = $room->players()->create(['name' => $name, 'last_seen_at' => date('Y-m-d H:i:s')]);
            $room->touch();
            $this->signInAs($player);
            return null;
        });

        if ($error !== null) {
            return $this->renderMultiplayer($response, $back + ['join_error' => $error], 422);
        }

        return self::redirect($response, '/sala/' . $room->code);
    }

    /**
     * The room page: lobby, game and final leaderboard (JavaScript switches between them).
     * If you are not in this room, sends you to the join form with the code filled in.
     */
    public function room(Request $request, Response $response, array $args): Response
    {
        $room = Room::findByCode($args['code']);
        if ($room === null) {
            return $this->renderMultiplayer($response, ['join_error' => 'Esa sala ya no existe.'], 404);
        }
        if ($room->code !== $args['code']) {
            return self::redirect($response, '/sala/' . $room->code);   // /sala/abcd → /sala/ABCD
        }

        $me = $this->currentPlayer();
        if ($me === null || $me->room_id !== $room->id) {
            return self::redirect($response, '/rivales?codigo=' . $room->code);
        }

        $base = (string) $request->getUri()->withPath('')->withQuery('')->withFragment('');
        // Behind Caddy/HTTPS the URI arrives as http://: fix the scheme for the invite link.
        if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
            $base = preg_replace('#^http://#', 'https://', $base);
        }

        return $this->view->render($response, 'room.tpl', [
            'title' => $room->solo ? 'Partida en solitario' : 'Sala ' . $room->code,
            'room' => $room,
            'me' => $me,
            'inviteLink' => $base . '/sala/' . $room->code,
            'difficulties' => Game::DIFFICULTIES,
        ]);
    }

    public function leave(Request $request, Response $response, array $args): Response
    {
        $this->leaveCurrentRoom();
        return self::redirect($response, '/');
    }

    // ---------------------------------------------------------------------
    // API for the room JavaScript (JSON)
    // ---------------------------------------------------------------------

    /**
     * The browser asks every 1-2 s: "anything new?".
     * ?since=N → only chat messages with id greater than N.
     */
    public function state(Request $request, Response $response, array $args): Response
    {
        [$room, $me, $error] = $this->roomAndPlayerForApi($response, $args['code']);
        if ($error) {
            return $error;
        }

        $me->touchLastSeen();
        if ($room->status === 'playing') {
            Game::advance($room);   // time up? then on to the next phase
        }

        $since = max(0, (int) ($request->getQueryParams()['since'] ?? 0));
        $messages = $room->solo ? collect() : $room->messages()
            ->where('id', '>', $since)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        return self::json($response, [
            'code' => $room->code,
            'status' => $room->status,
            'solo' => $room->solo,
            'me' => $me->id,
            'isHost' => $room->host_id === $me->id,
            'maxPlayers' => Room::MAX_PLAYERS,
            'difficulty' => $room->difficulty,
            'game' => $room->status === 'lobby' ? null : Game::stateFor($room, $me),
            'players' => $room->players()->get()->map(fn (Player $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'isHost' => $p->id === $room->host_id,
                'isOnline' => $p->isOnline(),
            ])->all(),
            'messages' => $messages->map(fn (Message $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'body' => $m->body,
                'time' => date('H:i', strtotime((string) $m->created_at)),
                'isMine' => $m->player_id === $me->id,
            ])->all(),
        ]);
    }

    public function sendMessage(Request $request, Response $response, array $args): Response
    {
        [$room, $me, $error] = $this->roomAndPlayerForApi($response, $args['code']);
        if ($error) {
            return $error;
        }

        $data = (array) $request->getParsedBody();
        $body = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) ($data['body'] ?? '')) ?? '');
        if ($body === '') {
            return self::json($response, ['error' => 'El mensaje está vacío.'], 422);
        }
        $body = mb_substr($body, 0, Message::MAX_LENGTH);

        // Anti-spam: at most one message per second per player.
        $last = Message::where('player_id', $me->id)->orderByDesc('id')->value('created_at');
        if ($last !== null && strtotime((string) $last) >= time() - 1) {
            return self::json($response, ['error' => 'Más despacio 🙂'], 429);
        }

        $message = Message::create([
            'room_id' => $room->id,
            'player_id' => $me->id,
            'name' => $me->name,
            'body' => $body,
        ]);
        $room->touch();

        return self::json($response, ['ok' => true, 'id' => $message->id], 201);
    }

    /**
     * Only the host can start. Picks the 10 questions and starts the countdown.
     * In solo mode it also works as "play again" from the final screen.
     */
    public function start(Request $request, Response $response, array $args): Response
    {
        [$room, $me, $error] = $this->roomAndPlayerForApi($response, $args['code']);
        if ($error) {
            return $error;
        }
        if ($room->host_id !== $me->id) {
            return self::json($response, ['error' => 'Solo quien creó la sala puede empezar.'], 403);
        }
        if ($room->status === 'playing') {
            return self::json($response, ['ok' => true]);   // already started: nothing to do
        }
        if ($room->status === 'finished' && !$room->solo) {
            return self::json($response, ['error' => 'Primero volved a la sala.'], 409);
        }
        if (!$room->solo && $room->players()->count() < 2) {
            return self::json($response, ['error' => 'Hacen falta al menos 2 jugadores.'], 409);
        }

        $data = (array) $request->getParsedBody();
        $difficulty = (string) ($data['difficulty'] ?? $room->difficulty);
        if ($failure = Game::start($room, $difficulty)) {
            return self::json($response, ['error' => $failure], 500);
        }

        return self::json($response, ['ok' => true]);
    }

    /** The player answers: {"round": 3, "optionId": 1234}. */
    public function answer(Request $request, Response $response, array $args): Response
    {
        [$room, $me, $error] = $this->roomAndPlayerForApi($response, $args['code']);
        if ($error) {
            return $error;
        }
        $me->touchLastSeen();

        $data = (array) $request->getParsedBody();
        $failure = Game::answer($room, $me, (int) ($data['round'] ?? 0), (int) ($data['optionId'] ?? 0));
        if ($failure !== null) {
            return self::json($response, ['error' => $failure], 409);
        }
        return self::json($response, ['ok' => true]);
    }

    /** After a game, the host brings everyone back to the lobby to play again (new people can join). */
    public function backToLobby(Request $request, Response $response, array $args): Response
    {
        [$room, $me, $error] = $this->roomAndPlayerForApi($response, $args['code']);
        if ($error) {
            return $error;
        }
        if ($room->host_id !== $me->id) {
            return self::json($response, ['error' => 'Solo el anfitrión puede hacerlo.'], 403);
        }
        if ($room->status === 'finished') {
            $room->fill(['status' => 'lobby', 'phase' => null, 'round' => 0])->save();
        }
        return self::json($response, ['ok' => true]);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function createRoomWithHost(string $name, bool $solo): Room
    {
        Room::deleteAbandoned();
        $this->leaveCurrentRoom();

        return Capsule::connection()->transaction(function () use ($name, $solo) {
            $room = Room::create(['code' => Room::newCode(), 'status' => 'lobby', 'solo' => $solo]);
            $player = $room->players()->create(['name' => $name, 'last_seen_at' => date('Y-m-d H:i:s')]);
            $room->host_id = $player->id;
            $room->save();
            $this->signInAs($player);
            return $room;
        });
    }

    /** The player of this session, or null. If their room was deleted, clears the session. */
    private function currentPlayer(): ?Player
    {
        $id = $_SESSION['player_id'] ?? null;
        if ($id === null) {
            return null;
        }
        $player = Player::with('room')->find($id);
        if ($player === null && session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION['player_id']);
        }
        return $player;
    }

    private function signInAs(Player $player): void
    {
        session_regenerate_id(true);   // new session id on "sign in": prevents session fixation
        $_SESSION['player_id'] = $player->id;
        $_SESSION['player_name'] = $player->name;   // to prefill the form next time
    }

    /**
     * Removes the player from their room. If they were the host, the next
     * player (the one who has been there longest) takes over. If the room
     * ends up empty, it is deleted.
     */
    private function leaveCurrentRoom(): void
    {
        $me = $this->currentPlayer();
        unset($_SESSION['player_id']);
        if ($me === null) {
            return;
        }

        Capsule::connection()->transaction(function () use ($me) {
            $room = $me->room;
            $me->delete();

            $next = $room->players()->first();
            if ($next === null) {
                $room->delete();
                return;
            }
            if ($room->host_id === $me->id) {
                $room->host_id = $next->id;
            }
            $room->touch();
            $room->save();
        });
    }

    /**
     * For the API: finds room and player and CLOSES the session right away.
     * Closing it matters: PHP locks the session while it is open, so the same
     * player's polling and chat requests would wait for each other.
     *
     * @return array{0: ?Room, 1: ?Player, 2: ?Response}
     */
    private function roomAndPlayerForApi(Response $response, string $code): array
    {
        $me = $this->currentPlayer();
        session_write_close();

        $room = Room::findByCode($code);
        if ($room === null) {
            return [null, null, self::json($response, ['error' => 'room_not_found'], 404)];
        }
        if ($me === null || $me->room_id !== $room->id) {
            return [null, null, self::json($response, ['error' => 'not_in_room'], 403)];
        }
        return [$room, $me, null];
    }

    private function renderMultiplayer(Response $response, array $data, int $status = 200): Response
    {
        $data += [
            'title' => 'Jugar con rivales',
            'name' => $_SESSION['player_name'] ?? '',
            'code' => '',
            'player' => $this->currentPlayer(),
        ];
        return $this->view->render($response, 'multiplayer.tpl', $data)->withStatus($status);
    }

    private function renderSolo(Response $response, array $data, int $status = 200): Response
    {
        $data += [
            'title' => 'Jugar solo',
            'name' => $_SESSION['player_name'] ?? '',
            'difficulty' => 'mixed',
            'difficulties' => Game::DIFFICULTIES,
            'player' => $this->currentPlayer(),
        ];
        return $this->view->render($response, 'solo.tpl', $data)->withStatus($status);
    }

    /** Removes extra spaces and control characters. */
    private static function cleanName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $name) ?? '';
        $name = preg_replace('/\s+/u', ' ', $name) ?? '';
        return trim($name);
    }

    /** Validation message shown to the player (Spanish), or null if the name is fine. */
    private static function nameError(string $name): ?string
    {
        if ($name === '') {
            return 'Escribe tu nombre.';
        }
        if (mb_strlen($name) > 20) {
            return 'El nombre es muy largo (máximo 20 letras).';
        }
        return null;
    }

    private static function redirect(Response $response, string $url): Response
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
