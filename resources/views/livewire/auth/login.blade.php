<div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-950 px-4">
    <div class="w-full max-w-sm bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-6">
        <div class="flex items-center gap-2 mb-6 justify-center">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-indigo-700 flex items-center justify-center shadow-sm shadow-indigo-600/30">
                <x-heroicon-o-receipt-percent class="w-4 h-4 text-white" />
            </div>
            <span class="font-semibold text-gray-900 dark:text-gray-100 text-lg">{{ config('app.name') }}</span>
        </div>

        <form wire:submit="submit" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Usuario</label>
                <input
                    type="text"
                    wire:model="username"
                    autofocus
                    required
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contraseña</label>
                <input
                    type="password"
                    wire:model="password"
                    required
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                >
            </div>

            @if ($error)
                <p class="text-sm text-red-600 dark:text-red-400">{{ $error }}</p>
            @endif

            <button
                type="submit"
                class="w-full rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600 hover:shadow-lg hover:shadow-indigo-600/40 active:scale-[0.98] transition-all"
            >
                <span wire:loading.remove wire:target="submit">Ingresar</span>
                <span wire:loading wire:target="submit">Ingresando...</span>
            </button>
        </form>

        @if (app()->environment('local'))
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-4 text-center">Usuario inicial: admin / admin</p>
        @else
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-4 text-center">¿Olvidaste tu contraseña? Pedile a un administrador que te la reinicie desde Usuarios.</p>
        @endif
    </div>
</div>
