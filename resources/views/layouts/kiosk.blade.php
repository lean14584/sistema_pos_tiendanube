<!DOCTYPE html>
<html lang="es" class="h-full antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Consultar precios · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-gradient-to-br from-indigo-600 via-indigo-500 to-cyan-500 text-gray-900">
    {{ $slot }}
</body>
</html>
