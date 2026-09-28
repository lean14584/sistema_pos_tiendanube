@php
    $inputClass = 'w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-sky-400 transition';
    $sectionTitle = 'text-xs font-bold text-sky-700 dark:text-sky-400 uppercase tracking-wider';
    $sectionDivider = 'border-t border-sky-200/70 dark:border-gray-800 pt-5 mt-5';
    $label = 'block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1';
@endphp

<form wire:submit="save" class="max-w-5xl">
    <div class="rounded-2xl border border-sky-100 dark:border-gray-800 bg-sky-50/50 dark:bg-gray-900 shadow-sm p-5 sm:p-7">

        <div class="space-y-4">
            <h3 class="{{ $sectionTitle }}">Datos generales</h3>

            <div class="max-w-md">
                <label class="{{ $label }}">Nombre *</label>
                <input
                    type="text"
                    wire:model="name"
                    required
                    class="{{ $inputClass }}"
                    placeholder="Bebidas"
                >
                @error('name') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="{{ $label }}">Descripción</label>
                <textarea
                    wire:model="description"
                    rows="2"
                    class="{{ $inputClass }}"
                ></textarea>
                @error('description') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
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
                href="{{ route('categories.index') }}"
                wire:navigate
                class="rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-5 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-all"
            >
                Cancelar
            </a>
        </div>
    </div>
</form>
