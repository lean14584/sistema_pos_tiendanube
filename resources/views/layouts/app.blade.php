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
<body
    x-data="{ sidebarOpen: false }"
    class="min-h-full flex bg-gradient-to-br from-gray-50 via-white to-indigo-50/40 text-gray-900 dark:from-gray-950 dark:via-gray-950 dark:to-indigo-950/20 dark:text-gray-100"
>
    @include('layouts.partials.sidebar')

    <div class="flex-1 min-w-0 flex flex-col min-h-screen">
        <header class="lg:hidden h-14 flex items-center gap-3 px-4 border-b border-gray-200 bg-white/80 backdrop-blur dark:border-gray-800 dark:bg-gray-900/80 sticky top-0 z-20">
            <button
                type="button"
                x-on:click="sidebarOpen = true"
                aria-label="Abrir menú"
                class="p-1.5 -ml-1.5 rounded-md text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
            >
                <x-heroicon-o-bars-3 class="w-6 h-6" />
            </button>
            <span class="font-semibold text-gray-900 dark:text-gray-100 tracking-tight">{{ config('app.name') }}</span>
        </header>

        <main class="flex-1 overflow-y-auto">
            @include('layouts.partials.flash-toasts')
            {{ $slot }}
        </main>
    </div>
</body>
</html>
