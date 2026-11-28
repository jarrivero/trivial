{extends file="layout.tpl"}

{block name=contenido}
    <h1 class="h3 fw-bold mb-3">Jugar con rivales</h1>

    {if $jugador}
        <div class="alert alert-warning small">
            Ya estás en la sala <strong class="font-monospace">{$jugador->sala->codigo}</strong>.
            <a href="/sala/{$jugador->sala->codigo}" class="alert-link">Volver a ella</a>.
            Si creas o te unes a otra, saldrás de esa.
        </div>
    {/if}

    {* --- Unirse: va primero si vienes de un enlace de invitación --- *}
    <div class="card shadow-sm mb-4 {if $codigo}border-primary{/if}">
        <div class="card-body">
            <h2 class="h5 card-title">Unirme a una sala</h2>
            <p class="text-body-secondary small mb-3">Pide el código de 4 letras a quien la ha creado.</p>
            {if !empty($error_unirse)}<div class="alert alert-danger py-2">{$error_unirse}</div>{/if}
            <form method="post" action="/unirse" class="d-grid gap-3" autocomplete="off">
                <div>
                    <label for="codigo" class="form-label">Código de la sala</label>
                    <input type="text" class="form-control form-control-lg text-uppercase font-monospace text-center"
                           id="codigo" name="codigo" value="{$codigo}" required maxlength="4" minlength="4"
                           pattern="[A-Za-z]{literal}{4}{/literal}" inputmode="text" autocapitalize="characters"
                           placeholder="ABCD" style="letter-spacing:.4em">
                </div>
                <div>
                    <label for="nombre-unirse" class="form-label">Tu nombre</label>
                    <input type="text" class="form-control form-control-lg" id="nombre-unirse" name="nombre"
                           value="{$nombre}" required maxlength="20" autocomplete="nickname" {if $codigo}autofocus{/if}>
                </div>
                <button type="submit" class="btn btn-primary btn-lg py-3">Unirme</button>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="h5 card-title">Crear una sala nueva</h2>
            <p class="text-body-secondary small mb-3">Te daremos un código para que se unan tus amigos.</p>
            {if !empty($error_crear)}<div class="alert alert-danger py-2">{$error_crear}</div>{/if}
            <form method="post" action="/salas" class="d-grid gap-3" autocomplete="off">
                <div>
                    <label for="nombre-crear" class="form-label">Tu nombre</label>
                    <input type="text" class="form-control form-control-lg" id="nombre-crear" name="nombre"
                           value="{$nombre}" required maxlength="20" autocomplete="nickname">
                </div>
                <button type="submit" class="btn btn-outline-primary btn-lg py-3">Crear sala</button>
            </form>
        </div>
    </div>

    <div class="text-center mt-4"><a href="/" class="link-secondary">← Volver al inicio</a></div>
{/block}
