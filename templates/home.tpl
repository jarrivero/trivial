{extends file="layout.tpl"}

{block name=contenido}
    <div class="text-center mb-4">
        <h1 class="display-6 fw-bold">{$titulo}</h1>
        <p class="text-body-secondary">Preguntas, rapidez y piques con los amigos.</p>
    </div>

    {if $jugador}
        {* Reconexión: la sesión recuerda en qué sala estabas *}
        <div class="alert alert-primary d-flex flex-column gap-2">
            <div>Estás en la sala <strong class="font-monospace">{$jugador->sala->codigo}</strong> como <strong>{$jugador->nombre}</strong>.</div>
            <a class="btn btn-primary btn-lg w-100" href="/sala/{$jugador->sala->codigo}">Volver a la sala</a>
        </div>
    {/if}

    <div class="d-grid gap-3">
        <button type="button" class="btn btn-outline-secondary btn-lg py-3 w-100" disabled>
            Jugar solo <small class="d-block fs-6">(próximamente)</small>
        </button>
        <a class="btn btn-primary btn-lg py-3 w-100" href="/rivales">Jugar con rivales</a>
    </div>
{/block}
