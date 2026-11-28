{extends file="layout.tpl"}

{block name=head}
<style>
    /* Kahoot-style answer buttons: big, coloured and with a shape */
    .answer-btn { min-height: 5.5rem; font-size: 1.05rem; font-weight: 600; white-space: normal; }
    .answer-btn .shape { font-size: 1.3rem; line-height: 1; }
    .answer-btn.dimmed { opacity: .35; }
    .answer-btn.chosen { outline: 4px solid var(--bs-body-color); outline-offset: 2px; }
    #timer-bar { transition: width .2s linear; }
</style>
{/block}

{block name=content}
<div id="room" data-code="{$room->code}" data-invite-link="{$inviteLink}">

    {* ================================================================
       LOBBY: waiting room before the game starts
       ================================================================ *}
    <div id="lobby-view" class="d-none">
        <div class="card shadow-sm mb-3 text-center">
            <div class="card-body">
                <div class="text-body-secondary small">Código de la sala</div>
                <div class="display-4 fw-bold font-monospace" style="letter-spacing:.2em">{$room->code}</div>
                <div class="d-flex gap-2 justify-content-center mt-2">
                    <button type="button" class="btn btn-outline-primary" id="share-btn">📤 Invitar</button>
                    <button type="button" class="btn btn-outline-secondary" id="copy-btn">Copiar enlace</button>
                </div>
                <div class="small text-success mt-2 d-none" id="copied">¡Enlace copiado!</div>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Jugadores</span>
                <span class="badge text-bg-secondary" id="player-count">–</span>
            </div>
            <ul class="list-group list-group-flush" id="player-list">
                <li class="list-group-item text-body-secondary">Cargando…</li>
            </ul>
        </div>

        {* --- Start (host only) or waiting message --- *}
        <div class="mb-4">
            <div class="d-none" id="host-controls">
                <label for="difficulty" class="form-label fw-semibold">Dificultad</label>
                <select id="difficulty" class="form-select form-select-lg mb-3">
                    {foreach $difficulties as $value => $label}
                        <option value="{$value}" {if $value == $room->difficulty}selected{/if}>{$label}</option>
                    {/foreach}
                </select>
                <button type="button" class="btn btn-success btn-lg py-3 w-100" id="start-btn">Empezar</button>
                <div class="small text-body-secondary text-center mt-2 d-none" id="need-players">Hacen falta al menos 2 jugadores.</div>
                <div class="small text-danger text-center mt-2 d-none" id="start-error"></div>
            </div>
            <div class="text-center text-body-secondary d-none" id="waiting-host">
                <span class="spinner-grow spinner-grow-sm text-primary me-1" aria-hidden="true"></span>
                Esperando a que <strong class="host-name"></strong> empiece la partida…
            </div>
        </div>
    </div>

    {* ================================================================
       GAME: countdown, question, answers and results
       ================================================================ *}
    <div id="game-view" class="d-none">
        <div class="d-flex justify-content-between align-items-center mb-2 small">
            <span class="fw-semibold" id="round-label"></span>
            <span class="text-body-secondary" id="category"></span>
        </div>

        {* Countdown before the first question *}
        <div class="card shadow-sm text-center py-5 mb-3 d-none" id="countdown">
            <div class="text-body-secondary">¡Prepárate!</div>
            <div class="display-1 fw-bold" id="countdown-number">3</div>
            <div class="text-body-secondary small">10 preguntas · <span id="countdown-difficulty"></span></div>
        </div>

        <div id="question-block" class="d-none">
            {* Time left *}
            <div class="d-flex align-items-center gap-2 mb-2">
                <div class="progress flex-grow-1" role="progressbar" aria-label="Tiempo" style="height:.8rem">
                    <div class="progress-bar" id="timer-bar" style="width:100%"></div>
                </div>
                <span class="fw-bold font-monospace text-end" id="seconds-left" style="min-width:2.5ch">20</span>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <p class="h5 mb-0" id="question-text"></p>
                </div>
            </div>

            <div class="row g-2 mb-3" id="answer-options"></div>

            <div class="text-center text-body-secondary small mb-3" id="answer-status"></div>
        </div>

        {* After each question: did you get it right? + leaderboard *}
        <div id="results-block" class="d-none">
            <div class="alert text-center fw-semibold mb-3" id="feedback"></div>
            <div class="card shadow-sm mb-3">
                <div class="card-header d-flex justify-content-between">
                    <span class="fw-semibold">Clasificación</span>
                    <span class="small text-body-secondary" id="next-in"></span>
                </div>
                <ol class="list-group list-group-flush list-group-numbered" id="leaderboard"></ol>
            </div>
        </div>
    </div>

    {* ================================================================
       FINISHED: final leaderboard
       ================================================================ *}
    <div id="finished-view" class="d-none">
        <div class="text-center mb-3">
            <div class="display-4" id="finished-emoji">🏆</div>
            <h2 class="h3 fw-bold mb-1" id="finished-title">¡Fin de la partida!</h2>
            <div class="text-body-secondary" id="finished-subtitle"></div>
        </div>
        <div class="card shadow-sm mb-3">
            <ul class="list-group list-group-flush" id="final-leaderboard"></ul>
        </div>
        <div class="d-grid gap-2 mb-4">
            <button type="button" class="btn btn-success btn-lg py-3 d-none" id="play-again-btn">Jugar otra vez</button>
            <div class="text-center text-body-secondary d-none" id="waiting-play-again">
                Esperando a que <strong class="host-name"></strong> prepare otra partida…
            </div>
            <a href="/" class="btn btn-outline-secondary d-none" id="home-btn">Volver al inicio</a>
        </div>
    </div>

    {* ================================================================
       CHAT: in the lobby, between questions and at the end (not in solo)
       ================================================================ *}
    <div class="card shadow-sm mb-4 d-none" id="chat-block">
        <div class="card-header fw-semibold">Chat</div>
        <div class="card-body overflow-auto p-2" id="chat" style="height: 30vh; min-height: 160px">
            <div class="text-body-secondary small text-center my-3" id="chat-empty">Aún no hay mensajes. ¡Saluda! 👋</div>
        </div>
        <div class="card-footer p-2">
            <form id="chat-form" class="d-flex gap-2" autocomplete="off">
                <input type="text" class="form-control" id="chat-input" maxlength="200" placeholder="Escribe un mensaje…"
                       enterkeyhint="send" required>
                <button class="btn btn-primary" type="submit">Enviar</button>
            </form>
            <div class="small text-danger mt-1 d-none" id="chat-error"></div>
        </div>
    </div>

    <div class="small text-warning-emphasis text-center mb-3 d-none" id="offline">
        Sin conexión con el servidor. Reintentando…
    </div>

    <form method="post" action="/rooms/{$room->code}/leave" class="text-center"
          onsubmit="return confirm('¿Seguro que quieres salir?')">
        <button type="submit" class="btn btn-link link-danger">{if $room->solo}Abandonar la partida{else}Salir de la sala{/if}</button>
    </form>
</div>
{/block}

{block name=scripts}
    <script src="/js/room.js?v=3"></script>
{/block}
