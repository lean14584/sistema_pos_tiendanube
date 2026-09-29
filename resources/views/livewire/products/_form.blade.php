@php
    $inputClass = 'w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-sky-400 transition';
    $sectionTitle = 'text-xs font-bold text-sky-700 dark:text-sky-400 uppercase tracking-wider';
    $sectionDivider = 'border-t border-sky-200/70 dark:border-gray-800 pt-5 mt-5';
    $label = 'block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1';
@endphp

<form wire:submit="save" class="max-w-5xl">
    <div class="rounded-2xl border border-sky-200 dark:border-gray-800 bg-sky-100/70 dark:bg-gray-900 shadow-sm p-5 sm:p-7">

        {{-- Sección: Datos generales --}}
        <div class="space-y-4">
            <h3 class="{{ $sectionTitle }}">Datos generales</h3>

            <div>
                <label class="{{ $label }}">Nombre *</label>
                <input
                    type="text"
                    wire:model="name"
                    required
                    class="{{ $inputClass }}"
                    placeholder="Notebook 14''"
                >
                @error('name') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            @if (config('features.sell_by_weight'))
            <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 px-4 py-3 cursor-pointer">
                <span>
                    <span class="block text-sm font-medium text-gray-800 dark:text-gray-200">Se vende por peso (kg)</span>
                    <span class="block text-xs text-gray-400 dark:text-gray-500">El precio se toma como precio por kilo. No lleva control de stock (se repone a granel). Se escanea con el código de balanza configurado en Configuración de Empresa.</span>
                </span>
                <input type="checkbox" wire:model.live="sold_by_weight" class="w-5 h-5 rounded border-gray-300 dark:border-gray-600 text-sky-600 focus:ring-sky-500">
            </label>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $label }}">{{ $sold_by_weight ? 'Código PLU (balanza) *' : 'SKU / Código' }}</label>
                    <input
                        type="text"
                        wire:model="sku"
                        class="{{ $inputClass }}"
                        placeholder="{{ $sold_by_weight ? '00023' : 'NB-14' }}"
                    >
                    @error('sku') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $label }}">{{ $sold_by_weight ? 'Precio de venta (por kg) *' : 'Precio de venta *' }}</label>
                    <input
                        type="number" min="0" step="0.01"
                        wire:model="price"
                        required
                        class="{{ $inputClass }}"
                        placeholder="0.00"
                    >
                    @error('price') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Sección: Precios y stock --}}
        <div class="space-y-4 {{ $sectionDivider }}">
            <h3 class="{{ $sectionTitle }}">Precios y stock</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $label }}">Precio de compra</label>
                    <input
                        type="number" min="0" step="0.01"
                        wire:model="cost_price"
                        class="{{ $inputClass }}"
                        placeholder="0.00"
                    >
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Si el precio de venta cae por debajo, el producto se marca en rojo.</p>
                </div>
                @unless($ocultarIva ?? false)
                    <div>
                        <label class="{{ $label }}">Alícuota de IVA *</label>
                        <select wire:model="iva_rate" class="{{ $inputClass }}">
                            @foreach (App\Enums\AlicuotaIva::cases() as $alicuota)
                                <option value="{{ $alicuota->value }}">{{ $alicuota->label() }}</option>
                            @endforeach
                        </select>
                        @error('iva_rate') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                    </div>
                @endunless
            </div>

            <div>
                <label class="{{ $label }}">Categoría</label>
                <select wire:model="category_id" class="{{ $inputClass }} sm:max-w-xs">
                    <option value="">Sin categoría</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $label }}">Stock en {{ $sucursalActiva?->name ?? 'tu sucursal' }}</label>
                    @if ($sold_by_weight)
                        <p class="px-3 py-2.5 text-sm text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-800/60 rounded-xl">Sin control de stock (se vende por peso)</p>
                    @else
                        <input
                            type="number" min="0" step="1"
                            wire:model="stock"
                            class="{{ $inputClass }}"
                            placeholder="0"
                        >
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">El stock se maneja por sucursal — este valor es solo el de la sucursal activa.</p>
                    @endif
                </div>
                <div>
                    <label class="{{ $label }}">Stock mínimo</label>
                    <input
                        type="number" min="0" step="1"
                        wire:model="min_stock"
                        class="{{ $inputClass }}"
                        placeholder="0"
                    >
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Si el stock cae por debajo, el producto se marca en rojo.</p>
                </div>
            </div>
        </div>

        {{-- Sección: Imagen y descripción --}}
        <div class="space-y-4 {{ $sectionDivider }}">
            <h3 class="{{ $sectionTitle }}">Imagen y descripción</h3>

            <div>
                <label class="{{ $label }}">Foto del producto</label>
                <div class="flex items-center gap-4">
                    <div class="w-24 h-24 shrink-0 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden flex items-center justify-center">
                        @if ($image)
                            <img src="{{ $image->temporaryUrl() }}" alt="Vista previa" class="w-full h-full object-cover">
                        @elseif ($existingImageUrl)
                            <img src="{{ $existingImageUrl }}" alt="Foto actual" class="w-full h-full object-cover">
                        @else
                            <x-heroicon-o-photo class="w-8 h-8 text-gray-300 dark:text-gray-600" />
                        @endif
                    </div>
                    <div class="flex-1">
                        <input type="file" wire:model="image" accept="image/*"
                            class="block w-full text-sm text-gray-600 dark:text-gray-400 file:mr-3 file:rounded-lg file:border-0 file:bg-sky-50 dark:file:bg-sky-500/10 file:px-3 file:py-2 file:text-sm file:font-medium file:text-sky-700 dark:file:text-sky-300 hover:file:bg-sky-100 dark:hover:file:bg-sky-500/20 file:cursor-pointer">
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">JPG o PNG, hasta 4 MB.</p>
                        <div wire:loading wire:target="image" class="text-xs text-sky-600 dark:text-sky-400 mt-1">Subiendo...</div>
                        @error('image') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                        @if ($canRemoveImage && $existingImageUrl && ! $image)
                            <button type="button" wire:click="removeImage" class="text-xs text-red-600 hover:text-red-700 dark:text-red-400 mt-1">Quitar foto</button>
                        @endif
                    </div>
                </div>
            </div>

            <div>
                <label class="{{ $label }}">Descripción</label>
                <textarea
                    wire:model="description"
                    rows="2"
                    class="{{ $inputClass }}"
                ></textarea>
            </div>
        </div>

        <div class="flex gap-3 {{ $sectionDivider }}">
            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="save"
                class="rounded-lg bg-sky-600 hover:bg-sky-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm disabled:opacity-50 transition-all"
            >
                {{ $submitLabel }}
            </button>
            <a
                href="{{ route('products.index') }}"
                wire:navigate
                class="rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-5 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-all"
            >
                Cancelar
            </a>
        </div>
    </div>
</form>
