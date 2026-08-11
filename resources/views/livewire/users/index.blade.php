<div class="p-8 max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Usuarios</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Usuarios y roles con acceso al sistema</p>
        </div>
        <a href="{{ route('users.create') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600 hover:shadow-lg hover:shadow-indigo-600/40 active:scale-[0.98] transition-all">
            <x-heroicon-o-plus class="w-4 h-4" />
            Nuevo usuario
        </a>
    </div>

    @if (session('error'))
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 rounded-lg p-4 text-sm text-red-800 dark:text-red-400 mb-6">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40">
        @if ($users->isEmpty())
            <div class="p-12 text-center text-gray-400 dark:text-gray-500">
                <x-heroicon-o-shield-check class="w-10 h-10 mx-auto mb-3 text-gray-300 dark:text-gray-700" />
                <p class="text-sm">Todavía no agregaste usuarios.</p>
            </div>
        @else
            <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800 bg-gray-100/80 dark:bg-gray-800/40">
                        <th class="px-5 py-3 font-medium">Nombre</th>
                        <th class="px-5 py-3 font-medium">Usuario</th>
                        <th class="px-5 py-3 font-medium">Rol</th>
                        <th class="px-5 py-3 font-medium">Estado</th>
                        <th class="px-5 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr wire:key="user-{{ $user->id }}" class="border-b border-gray-50 dark:border-gray-800/60 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="px-5 py-3 font-medium text-gray-900 dark:text-gray-100">
                                {{ $user->name }}
                                @if ($user->id === auth()->id())
                                    <span class="ml-2 text-xs text-indigo-600 dark:text-indigo-400">(vos)</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-gray-600 dark:text-gray-400">{{ $user->username }}</td>
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ $user->role->label() }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $user->active ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/20' : 'bg-gray-100 text-gray-700 ring-gray-500/20 dark:bg-gray-500/10 dark:text-gray-400 dark:ring-gray-500/20' }}">
                                    {{ $user->active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('users.edit', $user) }}" wire:navigate class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200 hover:scale-110 transition-all">
                                        <x-heroicon-o-pencil class="w-4 h-4" />
                                    </a>
                                    <button
                                        wire:click="delete({{ $user->id }})"
                                        wire:confirm="¿Eliminar al usuario &quot;{{ $user->name }}&quot;?"
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
                @foreach ($users as $user)
                    <div wire:key="user-card-{{ $user->id }}" class="p-4">
                        <div class="flex items-start justify-between gap-3 mb-1.5">
                            <div class="min-w-0">
                                <p class="font-medium text-gray-900 dark:text-gray-100 truncate">
                                    {{ $user->name }}
                                    @if ($user->id === auth()->id())
                                        <span class="text-xs text-indigo-600 dark:text-indigo-400">(vos)</span>
                                    @endif
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $user->username }} · {{ $user->role->label() }}</p>
                            </div>
                            <div class="flex gap-1 shrink-0">
                                <a href="{{ route('users.edit', $user) }}" wire:navigate class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800">
                                    <x-heroicon-o-pencil class="w-4 h-4" />
                                </a>
                                <button
                                    wire:click="delete({{ $user->id }})"
                                    wire:confirm="¿Eliminar al usuario &quot;{{ $user->name }}&quot;?"
                                    class="p-1.5 rounded-md text-gray-500 hover:bg-red-50 hover:text-red-600 dark:text-gray-400 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                                >
                                    <x-heroicon-o-trash class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $user->active ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/20' : 'bg-gray-100 text-gray-700 ring-gray-500/20 dark:bg-gray-500/10 dark:text-gray-400 dark:ring-gray-500/20' }}">
                            {{ $user->active ? 'Activo' : 'Inactivo' }}
                        </span>
                    </div>
                @endforeach
            </div>

            <div class="p-4 border-t border-gray-100 dark:border-gray-800">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</div>
