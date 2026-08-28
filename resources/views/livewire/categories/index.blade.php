<div class="p-8 max-w-5xl mx-auto">
    <x-page-header title="Categorías" subtitle="Organizá tus productos por categoría" icon="tag">
        <x-slot:actions>
            <a href="{{ route('categories.create') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-emerald-900/20 hover:bg-emerald-400 active:scale-[0.98] transition-all">
                <x-heroicon-o-plus class="w-4 h-4" /> Nueva categoría
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40">
        @if ($categories->isEmpty())
            <div class="p-12 text-center text-gray-400 dark:text-gray-500">
                <x-heroicon-o-tag class="w-10 h-10 mx-auto mb-3 text-gray-300 dark:text-gray-700" />
                <p class="text-sm">Todavía no agregaste categorías.</p>
                <a href="{{ route('categories.create') }}" wire:navigate class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300">
                    Agregar la primera
                </a>
            </div>
        @else
            <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800 bg-gray-100/80 dark:bg-gray-800/40">
                        <th class="px-5 py-3 font-medium">Nombre</th>
                        <th class="px-5 py-3 font-medium">Descripción</th>
                        <th class="px-5 py-3 font-medium">Productos</th>
                        <th class="px-5 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($categories as $category)
                        <tr wire:key="category-{{ $category->id }}" class="border-b border-gray-50 dark:border-gray-800/60 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="px-5 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $category->name }}</td>
                            <td class="px-5 py-3 text-gray-600 dark:text-gray-400">{{ $category->description ?: '—' }}</td>
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ $category->products_count }}</td>
                            <td class="px-5 py-3">
                                <div class="flex justify-end gap-2">
                                    <a
                                        href="{{ route('categories.edit', $category) }}"
                                        wire:navigate
                                        class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200 hover:scale-110 transition-all"
                                    >
                                        <x-heroicon-o-pencil class="w-4 h-4" />
                                    </a>
                                    <button
                                        x-on:click="confirmThen('¿Eliminar la categoría ' + @js($category->name) + '?', () => $wire.delete({{ $category->id }}))"
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
                @foreach ($categories as $category)
                    <div wire:key="category-card-{{ $category->id }}" class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ $category->name }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ $category->description ?: '—' }}</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $category->products_count }} producto{{ $category->products_count === 1 ? '' : 's' }}</p>
                            </div>
                            <div class="flex gap-1 shrink-0">
                                <a
                                    href="{{ route('categories.edit', $category) }}"
                                    wire:navigate
                                    class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
                                >
                                    <x-heroicon-o-pencil class="w-4 h-4" />
                                </a>
                                <button
                                    x-on:click="confirmThen('¿Eliminar la categoría ' + @js($category->name) + '?', () => $wire.delete({{ $category->id }}))"
                                    class="p-1.5 rounded-md text-gray-500 hover:bg-red-50 hover:text-red-600 dark:text-gray-400 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                                >
                                    <x-heroicon-o-trash class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
