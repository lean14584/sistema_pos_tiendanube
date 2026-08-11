<div class="p-8 max-w-3xl mx-auto">
    <div class="flex items-center gap-3 mb-2">
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Tiendanube</h1>
        @if ($configurado)
            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 px-2.5 py-0.5 text-xs font-medium">
                <span class="w-1.5 h-1.5 rounded-full bg-current"></span> Conectado
            </span>
        @else
            <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 px-2.5 py-0.5 text-xs font-medium">
                Sin configurar
            </span>
        @endif
    </div>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">
        Conectá tu tienda online para importar productos y pedidos, y sincronizar el stock.
    </p>

    @if (session('status'))
        <div class="mb-6 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 px-4 py-3 text-sm">
            {{ session('status') }}
        </div>
    @endif

    @if ($resultado)
        <div class="mb-6 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 px-4 py-3 text-sm">
            {{ $resultado }}
        </div>
    @endif

    @if ($error)
        <div class="mb-6 rounded-lg bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-400 px-4 py-3 text-sm">
            {{ $error }}
        </div>
    @endif

    {{-- Credenciales --}}
    <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Conexión</h2>

        <form wire:submit="saveCredentials" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Store ID</label>
                <input type="text" wire:model="tiendanube_store_id" placeholder="1234567"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                @error('tiendanube_store_id') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Access Token</label>
                <input type="password" wire:model="tiendanube_token" placeholder="••••••••••••"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                @error('tiendanube_token') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1 flex items-start gap-1.5">
                    <x-heroicon-o-lock-closed class="w-4 h-4 shrink-0 mt-0.5" />
                    Se guarda en el servidor. Lo sacás autorizando tu app en el panel de Tiendanube.
                </p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Client secret <span class="text-gray-400 font-normal">(opcional, para validar webhooks)</span></label>
                <input type="password" wire:model="tiendanube_webhook_secret" placeholder="••••••••••••"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                @error('tiendanube_webhook_secret') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col sm:flex-row gap-2 pt-1">
                <button type="submit"
                    class="rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600 active:scale-[0.98] transition-all">
                    Guardar credenciales
                </button>
                <button type="button" wire:click="testConnection" wire:loading.attr="disabled" wire:target="testConnection"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-60 transition-colors">
                    <x-heroicon-o-signal class="w-4 h-4" />
                    <span wire:loading.remove wire:target="testConnection">Probar conexión</span>
                    <span wire:loading wire:target="testConnection">Probando...</span>
                </button>
            </div>
        </form>
    </div>

    {{-- Traer de Tiendanube --}}
    <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">Traer de Tiendanube <span class="text-sm font-normal text-gray-400">→ sistema</span></h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Necesita la conexión guardada.</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            @php
                $accionesTraer = [
                    ['m' => 'importProducts', 'i' => 'cube', 'l' => 'Importar productos'],
                    ['m' => 'importOrders', 'i' => 'shopping-cart', 'l' => 'Importar pedidos'],
                    ['m' => 'pullStock', 'i' => 'arrow-down-tray', 'l' => 'Traer stock'],
                    ['m' => 'importCategories', 'i' => 'tag', 'l' => 'Importar categorías'],
                    ['m' => 'importClients', 'i' => 'users', 'l' => 'Importar clientes'],
                ];
            @endphp
            @foreach ($accionesTraer as $a)
                <button wire:click="{{ $a['m'] }}" wire:loading.attr="disabled" wire:target="{{ $a['m'] }}"
                    class="flex flex-col items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-800 p-4 text-center hover:bg-gray-50 dark:hover:bg-gray-800/60 disabled:opacity-60 transition-colors">
                    <x-dynamic-component :component="'heroicon-o-'.$a['i']" class="w-6 h-6 text-indigo-500" />
                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $a['l'] }}</span>
                    <span wire:loading wire:target="{{ $a['m'] }}" class="text-xs text-gray-400">Procesando...</span>
                </button>
            @endforeach
        </div>
    </div>

    {{-- Enviar a Tiendanube --}}
    <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">Enviar a Tiendanube <span class="text-sm font-normal text-gray-400">sistema →</span></h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4 flex items-start gap-1.5">
            <x-heroicon-o-bolt class="w-4 h-4 shrink-0 mt-0.5 text-emerald-500" />
            Con la conexión guardada, cada cambio que hacés en un producto, categoría o cliente se envía solo a Tiendanube. Estos botones son para un envío masivo cuando quieras.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <button wire:click="pushProducts" wire:loading.attr="disabled" wire:target="pushProducts"
                class="flex flex-col items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-800 p-4 text-center hover:bg-gray-50 dark:hover:bg-gray-800/60 disabled:opacity-60 transition-colors">
                <x-heroicon-o-cloud-arrow-up class="w-6 h-6 text-indigo-500" />
                <span class="text-sm font-medium text-gray-800 dark:text-gray-200">Empujar productos</span>
                <span class="text-[11px] text-gray-400 dark:text-gray-500">crea/actualiza en la tienda</span>
                <span wire:loading wire:target="pushProducts" class="text-xs text-gray-400">Enviando...</span>
            </button>

            <button wire:click="pushCategories" wire:loading.attr="disabled" wire:target="pushCategories"
                class="flex flex-col items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-800 p-4 text-center hover:bg-gray-50 dark:hover:bg-gray-800/60 disabled:opacity-60 transition-colors">
                <x-heroicon-o-tag class="w-6 h-6 text-indigo-500" />
                <span class="text-sm font-medium text-gray-800 dark:text-gray-200">Empujar categorías</span>
                <span class="text-[11px] text-gray-400 dark:text-gray-500">crea las que falten en la tienda</span>
                <span wire:loading wire:target="pushCategories" class="text-xs text-gray-400">Enviando...</span>
            </button>

            <button wire:click="pushClients" wire:loading.attr="disabled" wire:target="pushClients"
                class="flex flex-col items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-800 p-4 text-center hover:bg-gray-50 dark:hover:bg-gray-800/60 disabled:opacity-60 transition-colors">
                <x-heroicon-o-users class="w-6 h-6 text-indigo-500" />
                <span class="text-sm font-medium text-gray-800 dark:text-gray-200">Empujar clientes</span>
                <span class="text-[11px] text-gray-400 dark:text-gray-500">crea/actualiza en la tienda</span>
                <span wire:loading wire:target="pushClients" class="text-xs text-gray-400">Enviando...</span>
            </button>

            <button wire:click="syncStock" wire:loading.attr="disabled" wire:target="syncStock"
                class="flex flex-col items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-800 p-4 text-center hover:bg-gray-50 dark:hover:bg-gray-800/60 disabled:opacity-60 transition-colors">
                <x-heroicon-o-arrow-path class="w-6 h-6 text-indigo-500" />
                <span class="text-sm font-medium text-gray-800 dark:text-gray-200">Enviar stock</span>
                <span class="text-[11px] text-gray-400 dark:text-gray-500">stock local → tienda</span>
                <span wire:loading wire:target="syncStock" class="text-xs text-gray-400">Enviando...</span>
            </button>
        </div>
    </div>

    {{-- Sincronización automática --}}
    <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">Sincronización automática <span class="text-sm font-normal text-gray-400">(webhooks)</span></h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
            Cuando en la tienda entra una venta o cambia un producto, cliente o categoría, se refleja solo en el sistema. Requiere que el sistema tenga una URL pública.
        </p>

        <div class="flex flex-col sm:flex-row gap-2">
            <button wire:click="enableWebhooks" wire:loading.attr="disabled" wire:target="enableWebhooks"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600 disabled:opacity-60 transition-all">
                <x-heroicon-o-bolt class="w-4 h-4" />
                <span wire:loading.remove wire:target="enableWebhooks">Activar</span>
                <span wire:loading wire:target="enableWebhooks">Activando...</span>
            </button>
            <button wire:click="disableWebhooks" wire:loading.attr="disabled" wire:target="disableWebhooks"
                class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-60 transition-colors">
                <span wire:loading.remove wire:target="disableWebhooks">Desactivar</span>
                <span wire:loading wire:target="disableWebhooks">Desactivando...</span>
            </button>
        </div>
    </div>
</div>
