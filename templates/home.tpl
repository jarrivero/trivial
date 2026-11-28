{extends file="layout.tpl"}

{block name=content}
    <div class="text-center mb-4">
        <h1 class="display-6 fw-bold">{$title}</h1>
        <p class="text-body-secondary">Preguntas, rapidez y piques con los amigos.</p>
    </div>

    {if $player}
        {* Reconnect: the session remembers which room you were in *}
        <div class="alert alert-primary d-flex flex-column gap-2">
            {if $player->room->solo}
                <div>Tienes una partida en solitario a medias, <strong>{$player->name}</strong>.</div>
                <a class="btn btn-primary btn-lg w-100" href="/sala/{$player->room->code}">Volver a la partida</a>
            {else}
                <div>Estás en la sala <strong class="font-monospace">{$player->room->code}</strong> como <strong>{$player->name}</strong>.</div>
                <a class="btn btn-primary btn-lg w-100" href="/sala/{$player->room->code}">Volver a la sala</a>
            {/if}
        </div>
    {/if}

    <div class="d-grid gap-3">
        <a class="btn btn-outline-primary btn-lg py-3 w-100" href="/solo">Jugar solo</a>
        <a class="btn btn-primary btn-lg py-3 w-100" href="/rivales">Jugar con rivales</a>
    </div>

    <p class="text-center small text-body-secondary mt-4 mb-0">
        10 preguntas, 20 segundos cada una. Cuanto antes aciertes, más puntos (de 500 a 1000).
    </p>
{/block}
