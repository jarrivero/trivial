{extends file="layout.tpl"}

{block name=contenido}
    <div class="text-center mb-4">
        <h1 class="display-6 fw-bold">{$titulo}</h1>
        <p class="text-body-secondary">Esqueleto listo. Aquí irá el menú de inicio.</p>
    </div>

    {* Botones de muestra para comprobar tamaños táctiles; se activan en la Fase 2 *}
    <div class="d-grid gap-3">
        <button type="button" class="btn btn-primary btn-lg py-3 w-100" disabled>Jugar solo</button>
        <button type="button" class="btn btn-outline-primary btn-lg py-3 w-100" disabled>Jugar con rivales</button>
    </div>
{/block}
