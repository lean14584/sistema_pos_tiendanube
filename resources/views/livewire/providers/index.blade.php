<div class="p-8 max-w-6xl mx-auto">
    <x-page-header title="Proveedores" subtitle="Gestioná los datos de tus proveedores" icon="truck">
        <x-slot:actions>
            @if (\App\Support\Permissions::canAccess(auth()->user()->role, 'data-import'))
                <a href="{{ route('providers.import-saldo') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-white/15 border border-white/25 px-4 py-2 text-sm font-medium text-white hover:bg-white/25 active:scale-[0.98] transition-all">
                    <x-heroicon-o-scale class="w-4 h-4" /> Saldo inicial
                </a>
                <a href="{{ route('providers.import') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-white/15 border border-white/25 px-4 py-2 text-sm font-medium text-white hover:bg-white/25 active:scale-[0.98] transition-all">
                    <x-heroicon-o-arrow-up-tray class="w-4 h-4" /> Importar Excel
                </a>
            @endif
            <a href="{{ route('providers.create') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-emerald-900/20 hover:bg-emerald-400 active:scale-[0.98] transition-all">
                <x-heroicon-o-plus class="w-4 h-4" /> Nuevo proveedor
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4">
        <div class="relative">
            <x-heroicon-o-magnifying-glass class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
                type="text"
                wire:model.live.debounce.300ms="query"
                placeholder="Buscar por nombre, email, teléfono o CUIT..."
                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors"
            >
        </div>
    </div>

    <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40">
        @if (! $hasAnyProviders)
            <div class="p-12 text-center text-gray-400 dark:text-gray-500">
                <x-heroicon-o-truck class="w-10 h-10 mx-auto mb-3 text-gray-300 dark:text-gray-700" />
                <p class="text-sm">Todavía no agregaste proveedores.</p>
                <a href="{{ route('providers.create') }}" wire:navigate class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300">
                    Agregar el primero
                </a>
            </div>
        @elseif ($providers->isEmpty())
            <div class="p-12 text-center text-gray-400 dark:text-gray-500">
                <x-heroicon-o-magnifying-glass class="w-10 h-10 mx-auto mb-3 text-gray-300 dark:text-gray-700" />
                <p class="text-sm">Sin resultados para "{{ $query }}".</p>
            </div>
        @else
            <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800 bg-gray-100/80 dark:bg-gray-800/40">
                        <th class="px-5 py-3 font-medium">Nombre</th>
                        <th class="px-5 py-3 font-medium">Email</th>
                        <th class="px-5 py-3 font-medium">Teléfono</th>
                        <th class="px-5 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($providers as $provider)
                        <tr wire:key="provider-{{ $provider->id }}" class="border-b border-gray-50 dark:border-gray-800/60 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="px-5 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $provider->name }}</td>
                            <td class="px-5 py-3 text-gray-600 dark:text-gray-400">{{ $provider->email ?: '—' }}</td>
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ $provider->phone ?: '—' }}</td>
                            <td class="px-5 py-3">
                                <div class="flex justify-end gap-2">
                                    <a
                                        href="{{ route('providers.account', $provider) }}"
                                        wire:navigate
                                        title="Cuenta corriente"
                                        class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200 hover:scale-110 transition-all"
                                    >
                                        <x-heroicon-o-wallet class="w-4 h-4" />
                                    </a>
                                    <a
                                        href="{{ route('providers.edit', $provider) }}"
                                        wire:navigate
                                        class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200 hover:scale-110 transition-all"
                                    >
                                        <x-heroicon-o-pencil class="w-4 h-4" />
                                    </a>
                                    <button
                                        x-on:click="confirmThen('¿Eliminar al proveedor ' + @js($provider->name) + '?', () => $wire.delete({{ $provider->id }}))"
                                        wire:loading.attr="disabled"
                                        wire:target="delete"
                                        aria-label="Eliminar proveedor"
                                        class="p-1.5 rounded-md text-gray-500 hover:bg-red-50 hover:text-red-600 dark:text-gray-400 dark:hover:bg-red-500/10 dark:hover:text-red-400 hover:scale-110 transition-all disabled:opacity-50"
                                    >
                                        <x-heroicon-o-trash class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>

            <div class="sm:hidden divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($providers as $provider)
                    <div wire:key="provider-card-{{ $provider->id }}" class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ $provider->name }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ $provider->email ?: '—' }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $provider->phone ?: '—' }}</p>
                            </div>
                            <div class="flex gap-1 shrink-0">
                                <a
                                    href="{{ route('providers.account', $provider) }}"
                                    wire:navigate
                                    title="Cuenta corriente"
                                    class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
                                >
                                    <x-heroicon-o-wallet class="w-4 h-4" />
                                </a>
                                <a
                                    href="{{ route('providers.edit', $provider) }}"
                                    wire:navigate
                                    class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
                                >
                                    <x-heroicon-o-pencil class="w-4 h-4" />
                                </a>
                                <button
                                    x-on:click="confirmThen('¿Eliminar al proveedor ' + @js($provider->name) + '?', () => $wire.delete({{ $provider->id }}))"
                                    wire:loading.attr="disabled"
                                    wire:target="delete"
                                    aria-label="Eliminar proveedor"
                                    class="p-1.5 rounded-md text-gray-500 hover:bg-red-50 hover:text-red-600 dark:text-gray-400 dark:hover:bg-red-500/10 dark:hover:text-red-400 disabled:opacity-50"
                                >
                                    <x-heroicon-o-trash class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="p-4 border-t border-gray-100 dark:border-gray-800">
                {{ $providers->links() }}
            </div>
        @endif
    </div>
</div>
