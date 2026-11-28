{extends file="layout.tpl"}

{block name=contenido}
<div id="lobby" data-codigo="{$sala->codigo}" data-enlace="{$enlace}">

    {* --- Código para compartir --- *}
    <div class="card shadow-sm mb-3 text-center">
        <div class="card-body">
            <div class="text-body-secondary small">Código de la sala</div>
            <div class="display-4 fw-bold font-monospace" style="letter-spacing:.2em">{$sala->codigo}</div>
            <div class="d-flex gap-2 justify-content-center mt-2">
                <button type="button" class="btn btn-outline-primary" id="btn-compartir">📤 Invitar</button>
                <button type="button" class="btn btn-outline-secondary" id="btn-copiar">Copiar enlace</button>
            </div>
            <div class="small text-success mt-2 d-none" id="copiado">¡Enlace copiado!</div>
        </div>
    </div>

    {* --- Aviso cuando el creador pulsa Empezar (el juego llega en la Fase 3) --- *}
    <div class="alert alert-success d-none" id="aviso-empezada">
        <strong>¡La partida ha empezado!</strong><br>
        <span class="small">El juego todavía no está hecho (llega en la siguiente fase).</span>
    </div>

    {* --- Jugadores --- *}
    <div class="card shadow-sm mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Jugadores</span>
            <span class="badge text-bg-secondary" id="contador">–</span>
        </div>
        <ul class="list-group list-group-flush" id="jugadores">
            <li class="list-group-item text-body-secondary">Cargando…</li>
        </ul>
    </div>

    {* --- Empezar (solo el creador) o mensaje de espera --- *}
    <div class="mb-4" id="zona-empezar">
        <button type="button" class="btn btn-success btn-lg py-3 w-100 d-none" id="btn-empezar">Empezar</button>
        <div class="small text-body-secondary text-center mt-2 d-none" id="falta-gente">Hacen falta al menos 2 jugadores.</div>
        <div class="text-center text-body-secondary d-none" id="esperando">
            <span class="spinner-grow spinner-grow-sm text-primary me-1" aria-hidden="true"></span>
            Esperando a que <strong id="nombre-creador"></strong> empiece la partida…
        </div>
    </div>

    {* --- Chat --- *}
    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">Chat</div>
        <div class="card-body overflow-auto p-2" id="chat" style="height: 35vh; min-height: 180px">
            <div class="text-body-secondary small text-center my-3" id="chat-vacio">Aún no hay mensajes. ¡Saluda! 👋</div>
        </div>
        <div class="card-footer p-2">
            <form id="form-chat" class="d-flex gap-2" autocomplete="off">
                <input type="text" class="form-control" id="texto" maxlength="200" placeholder="Escribe un mensaje…"
                       enterkeyhint="send" required>
                <button class="btn btn-primary" type="submit">Enviar</button>
            </form>
            <div class="small text-danger mt-1 d-none" id="chat-error"></div>
        </div>
    </div>

    <div class="small text-warning-emphasis text-center mb-3 d-none" id="sin-conexion">
        Sin conexión con el servidor. Reintentando…
    </div>

    <form method="post" action="/sala/{$sala->codigo}/salir" class="text-center"
          onsubmit="return confirm('¿Seguro que quieres salir de la sala?')">
        <button type="submit" class="btn btn-link link-danger">Salir de la sala</button>
    </form>
</div>
{/block}

{block name=scripts}
    <script src="/js/lobby.js?v=2"></script>
{/block}
