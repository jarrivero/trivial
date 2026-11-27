<!doctype html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    {* Obligatorio para que el móvil no muestre la web "en miniatura" *}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0d6efd">
    <title>{block name=titulo}{$titulo|default:'Trivial'}{/block}</title>

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">

    {* Los únicos ajustes propios permitidos *}
    <style>
        /* iOS hace zoom al enfocar campos con letra < 16px */
        input, select, textarea, .form-control, .form-select { font-size: 16px; }
        /* Quita el retardo del doble toque en botones y enlaces-botón */
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

    {* Centrado y estrecho en escritorio; ancho completo en móvil *}
    <main class="container py-4">
        <div class="row justify-content-center">
            <div class="col-12 col-md-10 col-lg-7 col-xl-6">
                {block name=contenido}{/block}
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
            crossorigin="anonymous"></script>
    {block name=scripts}{/block}
</body>
</html>
