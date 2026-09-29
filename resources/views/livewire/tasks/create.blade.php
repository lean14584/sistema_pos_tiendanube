@php
    $inputClass = 'w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-sky-400 transition';
    $sectionTitle = 'text-xs font-bold text-sky-700 dark:text-sky-400 uppercase tracking-wider';
    $sectionDivider = 'border-t border-sky-200/70 dark:border-gray-800 pt-5 mt-5';
    $label = 'block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1';
@endphp

<div class="p-8 max-w-6xl mx-auto">
    <x-page-header title="Nueva tarea" icon="check-circle" />

    <form wire:submit="save" class="max-w-5xl">
        <div class="rounded-2xl border border-sky-200 dark:border-gray-800 bg-sky-100/70 dark:bg-gray-900 shadow-sm p-5 sm:p-7">

            <div class="space-y-4">
                <h3 class="{{ $sectionTitle }}">Datos generales</h3>

                <div>
                    <label class="{{ $label }}">Título *</label>
                    <input type="text" wire:model="title" required class="{{ $inputClass }}" placeholder="Contar caja chica">
                    @error('title') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="{{ $label }}">Descripción</label>
                    <textarea wire:model="description" rows="4" class="{{ $inputClass }}" placeholder="Detalle opcional..."></textarea>
                    @error('description') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="sm:max-w-xs">
                    <label class="{{ $label }}">Asignar a *</label>
                    <select wire:model="assigned_to" class="{{ $inputClass }}">
                        <option value="">Elegir usuario...</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role->label() }})</option>
                        @endforeach
                    </select>
                    @error('assigned_to') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex gap-3 {{ $sectionDivider }}">
                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="rounded-lg bg-sky-600 hover:bg-sky-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm disabled:opacity-50 transition-all">
                    Crear tarea
                </button>
                <a href="{{ route('tasks.index') }}" wire:navigate class="rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-5 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-all">
                    Cancelar
                </a>
            </div>
        </div>
    </form>
</div>
