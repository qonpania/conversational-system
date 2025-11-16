<!doctype html>
<html lang="es" class="dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    @vite(['resources/css/app.css', 'resources/js/app.js']) {{-- tu Tailwind de app --}}
    @filamentStyles {{-- estilos de Filament --}}
</head>

<body class="fi">
    {{ $slot }}
    @filamentScripts
</body>

</html>
