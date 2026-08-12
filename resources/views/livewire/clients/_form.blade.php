<form wire:submit="save" class="space-y-5 max-w-xl">
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre / Razón social *</label>
        <input
            type="text"
            wire:model="name"
            required
            class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors"
            placeholder="Acme S.A."
        >
        @error('name') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email *</label>
        <input
            type="email"
            wire:model="email"
            required
            class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors"
            placeholder="contacto@acme.com"
        >
        @error('email') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Teléfono</label>
            <input
                type="text"
                wire:model="phone"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">CUIT / ID fiscal</label>
            <input
                type="text"
                wire:model="tax_id"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors"
            >
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Dirección</label>
        <textarea
            wire:model="address"
            rows="2"
            class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors"
        ></textarea>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Condición frente al IVA *</label>
            <select
                wire:model="condicion_iva"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors"
            >
                @foreach ($condicionIvaOptions as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>
            @error('condicion_iva') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo de documento *</label>
            <select
                wire:model="tipo_documento"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors"
            >
                @foreach ($tipoDocumentoOptions as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>
            @error('tipo_documento') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Lista de precios</label>
        <select
            wire:model="price_list_id"
            class="w-full max-w-xs rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors"
        >
            <option value="">— Precio base (sin lista) —</option>
            @foreach ($priceLists as $list)
                <option value="{{ $list->id }}">{{ $list->name }} ({{ (float) $list->adjustment_percent > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($list->adjustment_percent, 2), '0'), '.') }}%)</option>
            @endforeach
        </select>
        @error('price_list_id') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="flex gap-3 pt-2">
        <button
            type="submit"
            class="rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600 hover:shadow-lg hover:shadow-indigo-600/40 active:scale-[0.98] transition-all"
        >
            {{ $submitLabel }}
        </button>
        <a
            href="{{ route('clients.index') }}"
            wire:navigate
            class="rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-400 dark:hover:border-gray-600 hover:shadow-md active:scale-[0.98] transition-all"
        >
            Cancelar
        </a>
    </div>
</form>
