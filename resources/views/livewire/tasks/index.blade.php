<div class="p-8 max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Tareas</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                {{ $isAdmin ? 'Seguimiento de las tareas asignadas a todo el equipo' : 'Tus tareas asignadas' }}
            </p>
        </div>
        @if ($isAdmin)
            <a href="{{ route('tasks.create') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600 hover:shadow-lg hover:shadow-indigo-600/40 active:scale-[0.98] transition-all">
                <x-heroicon-o-plus class="w-4 h-4" />
                Nueva tarea
            </a>
        @endif
    </div>

    <div class="flex gap-1.5 flex-wrap mb-5">
        <button wire:click="$set('filter', 'all')" class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors {{ $filter === 'all' ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800' }}">
            Todas
        </button>
        @foreach ($statuses as $s)
            <button wire:click="$set('filter', '{{ $s->value }}')" class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors {{ $filter === $s->value ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800' }}">
                {{ $s->label() }}
            </button>
        @endforeach
    </div>

    <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40">
        @if ($tasks->isEmpty())
            <div class="p-12 text-center text-gray-400 dark:text-gray-500">
                <x-heroicon-o-check-circle class="w-10 h-10 mx-auto mb-3 text-gray-300 dark:text-gray-700" />
                <p class="text-sm">{{ $isAdmin ? 'No hay tareas creadas.' : 'No tenés tareas asignadas.' }}</p>
            </div>
        @else
            <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800 bg-gray-100/80 dark:bg-gray-800/40">
                        <th class="px-5 py-3 font-medium">Título</th>
                        @if ($isAdmin)
                            <th class="px-5 py-3 font-medium">Asignada a</th>
                        @endif
                        <th class="px-5 py-3 font-medium">Estado</th>
                        <th class="px-5 py-3 font-medium">Creada</th>
                        <th class="px-5 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tasks as $task)
                        <tr wire:key="task-{{ $task->id }}" class="border-b border-gray-50 dark:border-gray-800/60 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="px-5 py-3">
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $task->title }}</p>
                                @if ($task->description)
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5 max-w-md truncate">{{ $task->description }}</p>
                                @endif
                            </td>
                            @if ($isAdmin)
                                <td class="px-5 py-3 text-gray-700 dark:text-gray-300">{{ $task->assignee->name ?? 'Usuario desconocido' }}</td>
                            @endif
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <x-status-badge :status="$task->status" />
                                    <select
                                        wire:change="updateStatus({{ $task->id }}, $event.target.value)"
                                        class="text-xs rounded-md border border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 py-1 pl-1.5 pr-6 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    >
                                        @foreach ($statuses as $s)
                                            <option value="{{ $s->value }}" @selected($task->status === $s)>{{ $s->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ $task->created_at->format('d/m/Y') }}</td>
                            <td class="px-5 py-3 text-right">
                                @if ($isAdmin)
                                    <button
                                        wire:click="delete({{ $task->id }})"
                                        wire:confirm="¿Eliminar la tarea &quot;{{ $task->title }}&quot;?"
                                        class="p-1.5 rounded-md text-gray-500 hover:bg-red-50 hover:text-red-600 dark:text-gray-400 dark:hover:bg-red-500/10 dark:hover:text-red-400 hover:scale-110 transition-all"
                                    >
                                        <x-heroicon-o-trash class="w-4 h-4" />
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>

            <div class="sm:hidden divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($tasks as $task)
                    <div wire:key="task-card-{{ $task->id }}" class="p-4">
                        <div class="flex items-start justify-between gap-3 mb-2">
                            <div class="min-w-0">
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $task->title }}</p>
                                @if ($task->description)
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5 truncate">{{ $task->description }}</p>
                                @endif
                                @if ($isAdmin)
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $task->assignee->name ?? 'Usuario desconocido' }}</p>
                                @endif
                            </div>
                            @if ($isAdmin)
                                <button
                                    wire:click="delete({{ $task->id }})"
                                    wire:confirm="¿Eliminar la tarea &quot;{{ $task->title }}&quot;?"
                                    class="shrink-0 p-1.5 rounded-md text-gray-500 hover:bg-red-50 hover:text-red-600 dark:text-gray-400 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                                >
                                    <x-heroicon-o-trash class="w-4 h-4" />
                                </button>
                            @endif
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <x-status-badge :status="$task->status" />
                                <select
                                    wire:change="updateStatus({{ $task->id }}, $event.target.value)"
                                    class="text-xs rounded-md border border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 py-1 pl-1.5 pr-6 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                >
                                    @foreach ($statuses as $s)
                                        <option value="{{ $s->value }}" @selected($task->status === $s)>{{ $s->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $task->created_at->format('d/m/Y') }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
