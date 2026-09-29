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
                <label class="{{ $label }}">Nombre / Razón social *</label>
                <input
                    type="text"
                    wire:model="name"
                    required
                    class="{{ $inputClass }}"
                    placeholder="Acme S.A."
                >
                @error('name') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $label }}">Email{{ $phoneRequired ? '' : ' *' }}</label>
                    <input
                        type="email"
                        wire:model="email"
                        @if(! $phoneRequired) required @endif
                        class="{{ $inputClass }}"
                        placeholder="contacto@acme.com"
                    >
                    @error('email') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $label }}">Celular{{ $phoneRequired ? ' *' : '' }}</label>
                    <input
                        type="text"
                        wire:model="phone"
                        @if($phoneRequired) required @endif
                        class="{{ $inputClass }}"
                    >
                    @error('phone') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="{{ $label }}">Dirección</label>
                <textarea
                    wire:model="address"
                    rows="2"
                    class="{{ $inputClass }}"
                ></textarea>
            </div>
        </div>

        {{-- Sección: Fiscal y ventas --}}
        <div class="space-y-4 {{ $sectionDivider }}">
            <h3 class="{{ $sectionTitle }}">Fiscal y ventas</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $label }}">Condición frente al IVA *</label>
                    <select wire:model="condicion_iva" class="{{ $inputClass }}">
                        @foreach ($condicionIvaOptions as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                    @error('condicion_iva') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $label }}">Tipo de documento *</label>
                    <select wire:model="tipo_documento" class="{{ $inputClass }}">
                        @foreach ($tipoDocumentoOptions as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                    @error('tipo_documento') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $label }}">CUIT / ID fiscal</label>
                    <input type="text" wire:model="tax_id" class="{{ $inputClass }}">
                </div>
                <div>
                    <x-select label="Lista de precios" wire:model="price_list_id">
                        <option value="">— Precio base (sin lista) —</option>
                        @foreach ($priceLists as $list)
                            <option value="{{ $list->id }}">{{ $list->name }} ({{ (float) $list->adjustment_percent > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($list->adjustment_percent, 2), '0'), '.') }}%)</option>
                        @endforeach
                    </x-select>
                    @error('price_list_id') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="{{ $label }}">Límite de crédito (cuenta corriente)</label>
                <input type="number" min="0" step="0.01" wire:model="credit_limit" placeholder="Sin límite" class="{{ $inputClass }} sm:max-w-xs">
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Vacío = sin límite. Si una venta a cuenta corriente lo supera, se bloquea.</p>
                @error('credit_limit') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
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
                href="{{ route('clients.index') }}"
                wire:navigate
                class="rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-5 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-all"
            >
                Cancelar
            </a>
        </div>
    </div>
</form>
