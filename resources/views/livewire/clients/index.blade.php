<div class="p-8 max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Clientes</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Gestioná los datos de tus clientes</p>
        </div>
        <a
            href="{{ route('clients.create') }}"
            wire:navigate
            class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600 hover:shadow-lg hover:shadow-indigo-600/40 active:scale-[0.98] transition-all"
        >
            <x-heroicon-o-plus class="w-4 h-4" />
            Nuevo cliente
        </a>
    </div>

    @if (session('error'))
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 rounded-lg p-4 text-sm text-red-800 dark:text-red-400 mb-6">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40">
        @if ($clients->isEmpty())
            <div class="p-12 text-center text-gray-400 dark:text-gray-500">
                <x-heroicon-o-users class="w-10 h-10 mx-auto mb-3 text-gray-300 dark:text-gray-700" />
                <p class="text-sm">Todavía no agregaste clientes.</p>
                <a href="{{ route('clients.create') }}" wire:navigate class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300">
                    Agregar el primero
                </a>
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
                    @foreach ($clients as $client)
                        <tr wire:key="client-{{ $client->id }}" class="border-b border-gray-50 dark:border-gray-800/60 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="px-5 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $client->name }}</td>
                            <td class="px-5 py-3 text-gray-600 dark:text-gray-400">{{ $client->email }}</td>
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ $client->phone ?: '—' }}</td>
                            <td class="px-5 py-3">
                                <div class="flex justify-end gap-2">
                                    <a
                                        href="{{ route('clients.account', $client) }}"
                                        wire:navigate
                                        title="Cuenta corriente"
                                        class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200 hover:scale-110 transition-all"
                                    >
                                        <x-heroicon-o-wallet class="w-4 h-4" />
                                    </a>
                                    <a
                                        href="{{ route('clients.edit', $client) }}"
                                        wire:navigate
                                        class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200 hover:scale-110 transition-all"
                                    >
                                        <x-heroicon-o-pencil class="w-4 h-4" />
                                    </a>
                                    <button
                                        wire:click="delete({{ $client->id }})"
                                        wire:confirm="¿Eliminar al cliente &quot;{{ $client->name }}&quot;?"
                                        class="p-1.5 rounded-md text-gray-500 hover:bg-red-50 hover:text-red-600 dark:text-gray-400 dark:hover:bg-red-500/10 dark:hover:text-red-400 hover:scale-110 transition-all"
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
                @foreach ($clients as $client)
                    <div wire:key="client-card-{{ $client->id }}" class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ $client->name }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ $client->email }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $client->phone ?: '—' }}</p>
                            </div>
                            <div class="flex gap-1 shrink-0">
                                <a
                                    href="{{ route('clients.account', $client) }}"
                                    wire:navigate
                                    title="Cuenta corriente"
                                    class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
                                >
                                    <x-heroicon-o-wallet class="w-4 h-4" />
                                </a>
                                <a
                                    href="{{ route('clients.edit', $client) }}"
                                    wire:navigate
                                    class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
                                >
                                    <x-heroicon-o-pencil class="w-4 h-4" />
                                </a>
                                <button
                                    wire:click="delete({{ $client->id }})"
                                    wire:confirm="¿Eliminar al cliente &quot;{{ $client->name }}&quot;?"
                                    class="p-1.5 rounded-md text-gray-500 hover:bg-red-50 hover:text-red-600 dark:text-gray-400 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                                >
                                    <x-heroicon-o-trash class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="p-4 border-t border-gray-100 dark:border-gray-800">
                {{ $clients->links() }}
            </div>
        @endif
    </div>
</div>
