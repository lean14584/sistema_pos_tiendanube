<div class="p-8 max-w-5xl mx-auto">
    <a href="{{ route('products.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 mb-6">
        <x-heroicon-o-arrow-left class="w-4 h-4" /> Productos
    </a>
    <x-page-header title="Ajustes de Stock" subtitle="Corregí el stock de un producto por rotura, vencimiento, conteo físico o merma — fuera del flujo normal de ventas y compras." icon="wrench" />

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 px-4 py-2.5 text-sm text-emerald-700 dark:text-emerald-300">{{ session('status') }}</div>
    @endif

    @php $inputClass = 'w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent'; @endphp

    <form wire:submit="save" class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md p-5 mb-8">
        <h2 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-4">Nuevo ajuste</h2>

        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Producto *</label>
                <select wire:model.live="product_id" class="{{ $inputClass }}">
                    <option value="">Seleccionar...</option>
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}">{{ $p->name }} (stock actual: {{ $p->stock }})</option>
                    @endforeach
                </select>
                @error('product_id') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Nuevo stock *</label>
                <input type="number" min="0" wire:model="new_stock" class="{{ $inputClass }}">
                @error('new_stock') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Motivo *</label>
                <select wire:model="reason" class="{{ $inputClass }}">
                    @foreach ($reasons as $r)
                        <option value="{{ $r->value }}">{{ $r->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-4">
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Notas (opcional)</label>
            <textarea wire:model="notes" rows="2" class="{{ $inputClass }}" placeholder="Detalle adicional, ej. qué contó, dónde se rompió, etc."></textarea>
            @error('notes') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="flex gap-3 mt-5">
            <button type="submit" class="rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md hover:from-indigo-700 hover:to-indigo-600">Registrar ajuste</button>
        </div>
    </form>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
        <select wire:model.live="filterProduct" class="{{ $inputClass }}">
            <option value="">Todos los productos</option>
            @foreach ($products as $p)
                <option value="{{ $p->id }}">{{ $p->name }}</option>
            @endforeach
        </select>
        <input type="date" wire:model.live="desde" class="{{ $inputClass }}" placeholder="Desde">
        <input type="date" wire:model.live="hasta" class="{{ $inputClass }}" placeholder="Hasta">
    </div>

    <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800/50">
                <tr class="text-left text-gray-500 dark:text-gray-400">
                    <th class="px-4 py-2.5 font-medium">Fecha</th>
                    <th class="px-4 py-2.5 font-medium">Producto</th>
                    <th class="px-4 py-2.5 font-medium">Stock</th>
                    <th class="px-4 py-2.5 font-medium">Motivo</th>
                    <th class="px-4 py-2.5 font-medium">Usuario</th>
                    <th class="px-4 py-2.5 font-medium">Notas</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($adjustments as $adj)
                    <tr class="border-t border-gray-100 dark:border-gray-800">
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $adj->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $adj->product->name ?? 'Producto eliminado' }}</td>
                        <td class="px-4 py-3 {{ $adj->delta >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                            {{ $adj->previous_stock }} → {{ $adj->new_stock }}
                            <span class="text-xs">({{ $adj->delta >= 0 ? '+' : '' }}{{ $adj->delta }})</span>
                        </td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $adj->reason->label() }}</td>
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $adj->user->name ?? 'Sistema' }}</td>
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $adj->notes ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400 dark:text-gray-500">Todavía no se registraron ajustes de stock.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>

        <div class="p-4 border-t border-gray-100 dark:border-gray-800">
            {{ $adjustments->links() }}
        </div>
    </div>
</div>
