<!DOCTYPE html>
<html lang="es" class="h-full antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <script>
        (function () {
            try {
                var t = localStorage.getItem('theme');
                if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full flex bg-gradient-to-br from-gray-50 via-white to-indigo-50/40 text-gray-900 dark:from-gray-950 dark:via-gray-950 dark:to-indigo-950/20 dark:text-gray-100">
    @include('layouts.partials.sidebar')

    <main class="flex-1 min-h-screen overflow-y-auto">
        {{ $slot }}
    </main>
</body>
</html>
