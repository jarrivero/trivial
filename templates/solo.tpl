{extends file="layout.tpl"}

{block name=content}
    <h1 class="h3 fw-bold mb-3">Jugar solo</h1>

    <div class="card shadow-sm">
        <div class="card-body">
            <p class="text-body-secondary small mb-3">10 preguntas, 20 segundos cada una. ¿Cuántos puntos sacas?</p>
            {if !empty($error)}<div class="alert alert-danger py-2">{$error}</div>{/if}
            <form method="post" action="/solo" class="d-grid gap-3" autocomplete="off">
                <div>
                    <label for="name" class="form-label">Tu nombre</label>
                    <input type="text" class="form-control form-control-lg" id="name" name="name"
                           value="{$name}" required maxlength="20" autocomplete="nickname">
                </div>
                <div>
                    <label for="difficulty" class="form-label">Dificultad</label>
                    <select id="difficulty" name="difficulty" class="form-select form-select-lg">
                        {foreach $difficulties as $value => $label}
                            <option value="{$value}" {if $value == $difficulty}selected{/if}>{$label}</option>
                        {/foreach}
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-lg py-3">¡A jugar!</button>
            </form>
        </div>
    </div>

    <div class="text-center mt-4"><a href="/" class="link-secondary">← Volver al inicio</a></div>
{/block}
