{extends file="layout.tpl"}

{block name=content}
    <h1 class="h3 fw-bold mb-3">Jugar con rivales</h1>

    {if $player}
        <div class="alert alert-warning small">
            {if $player->room->solo}
                Tienes una partida en solitario a medias.
                <a href="/sala/{$player->room->code}" class="alert-link">Volver a ella</a>.
            {else}
                Ya estás en la sala <strong class="font-monospace">{$player->room->code}</strong>.
                <a href="/sala/{$player->room->code}" class="alert-link">Volver a ella</a>.
            {/if}
            Si creas o te unes a otra, saldrás de esa.
        </div>
    {/if}

    {* --- Join: shown first, since invite links land here --- *}
    <div class="card shadow-sm mb-4 {if $code}border-primary{/if}">
        <div class="card-body">
            <h2 class="h5 card-title">Unirme a una sala</h2>
            <p class="text-body-secondary small mb-3">Pide el código de 4 letras a quien la ha creado.</p>
            {if !empty($join_error)}<div class="alert alert-danger py-2">{$join_error}</div>{/if}
            <form method="post" action="/join" class="d-grid gap-3" autocomplete="off">
                <div>
                    <label for="code" class="form-label">Código de la sala</label>
                    <input type="text" class="form-control form-control-lg text-uppercase font-monospace text-center"
                           id="code" name="code" value="{$code}" required maxlength="4" minlength="4"
                           pattern="[A-Za-z]{literal}{4}{/literal}" inputmode="text" autocapitalize="characters"
                           placeholder="ABCD" style="letter-spacing:.4em">
                </div>
                <div>
                    <label for="join-name" class="form-label">Tu nombre</label>
                    <input type="text" class="form-control form-control-lg" id="join-name" name="name"
                           value="{$name}" required maxlength="20" autocomplete="nickname" {if $code}autofocus{/if}>
                </div>
                <button type="submit" class="btn btn-primary btn-lg py-3">Unirme</button>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="h5 card-title">Crear una sala nueva</h2>
            <p class="text-body-secondary small mb-3">Te daremos un código para que se unan tus amigos.</p>
            {if !empty($create_error)}<div class="alert alert-danger py-2">{$create_error}</div>{/if}
            <form method="post" action="/rooms" class="d-grid gap-3" autocomplete="off">
                <div>
                    <label for="create-name" class="form-label">Tu nombre</label>
                    <input type="text" class="form-control form-control-lg" id="create-name" name="name"
                           value="{$name}" required maxlength="20" autocomplete="nickname">
                </div>
                <button type="submit" class="btn btn-outline-primary btn-lg py-3">Crear sala</button>
            </form>
        </div>
    </div>

    <div class="text-center mt-4"><a href="/" class="link-secondary">← Volver al inicio</a></div>
{/block}
