<div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-950 px-4">
    <div class="w-full max-w-sm bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-6">
        <div class="flex items-center gap-2 mb-6 justify-center">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-indigo-700 flex items-center justify-center shadow-sm shadow-indigo-600/30">
                <x-heroicon-o-receipt-percent class="w-4 h-4 text-white" />
            </div>
            <span class="font-semibold text-gray-900 dark:text-gray-100 text-lg">{{ config('app.name') }}</span>
        </div>

        @if ($sent)
            <p class="text-sm text-gray-600 dark:text-gray-300 text-center">
                Si ese email está cargado en el sistema, te vamos a mandar un link para elegir una contraseña nueva.
            </p>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                Ingresá el email que tenés cargado en tu usuario. Si no le cargaste un email a tu cuenta, pedile a un administrador que te reinicie la contraseña desde Usuarios.
            </p>

            <form wire:submit="submit" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                    <input
                        type="email"
                        wire:model="email"
                        autofocus
                        required
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                    >
                    @error('email') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>

                @if ($error)
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $error }}</p>
                @endif

                <button
                    type="submit"
                    class="w-full rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600 hover:shadow-lg hover:shadow-indigo-600/40 active:scale-[0.98] transition-all"
                >
                    <span wire:loading.remove wire:target="submit">Enviar link de recuperación</span>
                    <span wire:loading wire:target="submit">Enviando...</span>
                </button>
            </form>
        @endif

        <p class="text-xs text-gray-400 dark:text-gray-500 mt-4 text-center">
            <a href="{{ route('login') }}" wire:navigate class="text-indigo-600 dark:text-indigo-400 hover:underline">Volver a iniciar sesión</a>
        </p>
    </div>
</div>
