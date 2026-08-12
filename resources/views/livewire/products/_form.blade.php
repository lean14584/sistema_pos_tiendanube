<form wire:submit="save" class="space-y-5 max-w-xl">
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre *</label>
        <input
            type="text"
            wire:model="name"
            required
            class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors"
            placeholder="Notebook 14''"
        >
        @error('name') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">SKU / Código</label>
            <input
                type="text"
                wire:model="sku"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors"
                placeholder="NB-14"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Precio de venta *</label>
            <input
                type="number" min="0" step="0.01"
                wire:model="price"
                required
                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors"
                placeholder="0.00"
            >
            @error('price') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Alícuota de IVA *</label>
            <select
                wire:model="iva_rate"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            >
                @foreach (App\Enums\AlicuotaIva::cases() as $alicuota)
                    <option value="{{ $alicuota->value }}">{{ $alicuota->label() }}</option>
                @endforeach
            </select>
            @error('iva_rate') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Precio de compra</label>
            <input
                type="number" min="0" step="0.01"
                wire:model="cost_price"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors"
                placeholder="0.00"
            >
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Si el precio de venta cae por debajo, el producto se marca en rojo.</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Categoría</label>
            <select
                wire:model="category_id"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            >
                <option value="">Sin categoría</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Stock</label>
            <input
                type="number" min="0" step="1"
                wire:model="stock"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors"
                placeholder="0"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Stock mínimo</label>
            <input
                type="number" min="0" step="1"
                wire:model="min_stock"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors"
                placeholder="0"
            >
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Si el stock cae por debajo, el producto se marca en rojo.</p>
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Foto del producto</label>
        <div class="flex items-center gap-4">
            <div class="w-24 h-24 shrink-0 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 overflow-hidden flex items-center justify-center">
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
                    class="block w-full text-sm text-gray-600 dark:text-gray-400 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 dark:file:bg-indigo-500/10 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 dark:file:text-indigo-300 hover:file:bg-indigo-100 dark:hover:file:bg-indigo-500/20 file:cursor-pointer">
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">JPG o PNG, hasta 4 MB.</p>
                <div wire:loading wire:target="image" class="text-xs text-indigo-600 dark:text-indigo-400 mt-1">Subiendo...</div>
                @error('image') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                @if ($canRemoveImage && $existingImageUrl && ! $image)
                    <button type="button" wire:click="removeImage" class="text-xs text-red-600 hover:text-red-700 dark:text-red-400 mt-1">Quitar foto</button>
                @endif
            </div>
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descripción</label>
        <textarea
            wire:model="description"
            rows="2"
            class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors"
        ></textarea>
    </div>

    <div class="flex gap-3 pt-2">
        <button
            type="submit"
            class="rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600 hover:shadow-lg hover:shadow-indigo-600/40 active:scale-[0.98] transition-all"
        >
            {{ $submitLabel }}
        </button>
        <a
            href="{{ route('products.index') }}"
            wire:navigate
            class="rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-400 dark:hover:border-gray-600 hover:shadow-md active:scale-[0.98] transition-all"
        >
            Cancelar
        </a>
    </div>
</form>
