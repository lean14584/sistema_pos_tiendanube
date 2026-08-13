<div class="p-8 max-w-5xl mx-auto">
    <style>
        @media print {
            /* Ocultá todo menos la hoja de etiquetas */
            aside, header, .no-print { display: none !important; }
            body { background: #fff !important; }
            main { overflow: visible !important; }
            .labels-sheet { display: grid !important; }
            .label-cell {
                border: 1px solid #000 !important;
                break-inside: avoid;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            @page { margin: 8mm; }
        }
    </style>

    <div class="no-print">
        <a href="{{ route('products.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 mb-6">
            <x-heroicon-o-arrow-left class="w-4 h-4" />
            Productos
        </a>
        <x-page-header title="Etiquetas de precios" subtitle="Elegí los productos, cuántas etiquetas de cada uno y mandá a imprimir." icon="printer" />

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            {{-- Buscador / agregar --}}
            <div class="space-y-4">
                <div class="relative">
                    <x-heroicon-o-magnifying-glass class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                    <input
                        type="text"
                        wire:model.live.debounce.200ms="productQuery"
                        placeholder="Buscar producto por nombre o SKU..."
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                    >
                    @if (trim($productQuery) !== '')
                        <div class="absolute z-20 mt-1 w-full bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 shadow-lg max-h-64 overflow-y-auto">
                            @forelse ($this->productResults as $product)
                                <button type="button" wire:click="addProduct({{ $product->id }})" class="w-full flex items-center justify-between gap-3 px-3 py-2 text-left hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $product->name }}</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 shrink-0">${{ number_format($product->price, 2) }}</span>
                                </button>
                            @empty
                                <p class="p-3 text-sm text-gray-400 dark:text-gray-500">Sin resultados.</p>
                            @endforelse
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    <select wire:model="catToAdd" class="flex-1 rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Agregar una categoría entera...</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    <button type="button" wire:click="addCategory" class="rounded-lg bg-indigo-600 hover:bg-indigo-700 px-3 py-2 text-white text-sm font-medium">Agregar</button>
                </div>
            </div>

            {{-- Opciones --}}
            <div class="space-y-3 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Lista de precios</label>
                    <select wire:model.live="price_list_id" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Precio base</option>
                        @foreach ($priceLists as $list)
                            <option value="{{ $list->id }}">{{ $list->name }} ({{ (float) $list->adjustment_percent > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($list->adjustment_percent, 2), '0'), '.') }}%)</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Columnas por hoja</label>
                    <select wire:model.live="columns" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                    </select>
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" wire:model.live="showName" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500"> Mostrar nombre
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" wire:model.live="showSku" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500"> Mostrar SKU
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" wire:model.live="showCompany" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500"> Mostrar nombre del comercio
                </label>
            </div>
        </div>

        {{-- Selección --}}
        @if (count($selected) > 0)
            <div class="flex flex-wrap items-center gap-2 mb-4">
                @foreach ($selected as $id => $row)
                    <span class="inline-flex items-center gap-2 rounded-full bg-gray-100 dark:bg-gray-800 pl-3 pr-1.5 py-1 text-sm text-gray-700 dark:text-gray-200">
                        {{ $row['name'] }}
                        <input type="number" min="1" wire:model.live="selected.{{ $id }}.qty" class="w-14 rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-900 px-1.5 py-0.5 text-xs text-right focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        <button type="button" wire:click="removeProduct({{ $id }})" class="text-gray-400 hover:text-red-500">✕</button>
                    </span>
                @endforeach
            </div>

            <div class="flex items-center gap-3 mb-8">
                <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600">
                    <x-heroicon-o-printer class="w-4 h-4" /> Imprimir {{ $labels->count() }} etiqueta(s)
                </button>
                <button type="button" wire:click="clear" class="rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">Vaciar</button>
            </div>
        @else
            <p class="text-sm text-gray-400 dark:text-gray-500 mb-8">Buscá y agregá productos para generar las etiquetas.</p>
        @endif
    </div>

    {{-- Hoja imprimible --}}
    @if ($labels->count() > 0)
        <div class="labels-sheet grid gap-2" style="grid-template-columns: repeat({{ $columns }}, minmax(0, 1fr));">
            @foreach ($labels as $label)
                <div class="label-cell flex flex-col items-center justify-center text-center rounded border border-gray-300 dark:border-gray-700 px-2 py-3 bg-white dark:bg-gray-900">
                    @if ($showCompany)
                        <span class="text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400 truncate w-full">{{ $companyName }}</span>
                    @endif
                    @if ($showName)
                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100 leading-tight line-clamp-2">{{ $label['name'] }}</span>
                    @endif
                    <span class="text-xl font-bold text-gray-900 dark:text-gray-100 mt-1">${{ number_format($label['price'], 2) }}</span>
                    @if ($showSku && $label['sku'])
                        <span class="text-[11px] font-mono tracking-widest text-gray-600 dark:text-gray-300 mt-1">{{ $label['sku'] }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
