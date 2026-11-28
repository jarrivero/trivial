<!doctype html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    {* Required so phones do not show the site "zoomed out" *}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0d6efd">
    {* Tab icon (avoids the /favicon.ico request that logged 404s) *}
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎯</text></svg>">
    <title>{block name=title}{$title|default:'Trivial'}{/block}</title>

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">

    {* The only custom tweaks allowed *}
    <style>
        /* iOS zooms in when focusing fields with font size < 16px */
        input, select, textarea, .form-control, .form-select { font-size: 16px; }
        /* Removes the double-tap delay on buttons and button links */
        button, .btn, a { touch-action: manipulation; }
    </style>
    {block name=head}{/block}
</head>
<body class="bg-body-tertiary">

    <nav class="navbar bg-primary" data-bs-theme="dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/">🎯 Trivial</a>
        </div>
    </nav>

    {* Centered and narrow on desktop; full width on mobile *}
    <main class="container py-4">
        <div class="row justify-content-center">
            <div class="col-12 col-md-10 col-lg-7 col-xl-6">
                {block name=content}{/block}
            </div>
        </div>
    </main>

    <footer class="text-center small text-body-secondary pb-4 px-3">
        Algunas preguntas proceden de <a href="https://opentdb.com" class="link-secondary">Open Trivia DB</a>
        (traducidas, licencia <a href="https://creativecommons.org/licenses/by-sa/4.0/deed.es" class="link-secondary">CC BY-SA 4.0</a>).
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
            crossorigin="anonymous"></script>
    {block name=scripts}{/block}
</body>
</html>
