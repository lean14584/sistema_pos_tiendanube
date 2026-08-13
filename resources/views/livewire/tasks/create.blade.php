@php
    $inputClass = 'w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors';
@endphp

<div class="p-8 max-w-5xl mx-auto">
    <x-page-header title="Nueva tarea" icon="check-circle" />

    <form wire:submit="save" class="space-y-5 max-w-xl">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Título *</label>
            <input type="text" wire:model="title" required class="{{ $inputClass }}" placeholder="Contar caja chica">
            @error('title') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descripción</label>
            <textarea wire:model="description" rows="4" class="{{ $inputClass }}" placeholder="Detalle opcional..."></textarea>
            @error('description') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Asignar a *</label>
            <select wire:model="assigned_to" class="{{ $inputClass }}">
                <option value="">Elegir usuario...</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role->label() }})</option>
                @endforeach
            </select>
            @error('assigned_to') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600 hover:shadow-lg hover:shadow-indigo-600/40 active:scale-[0.98] transition-all">
                Crear tarea
            </button>
            <a href="{{ route('tasks.index') }}" wire:navigate class="rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-400 dark:hover:border-gray-600 hover:shadow-md active:scale-[0.98] transition-all">
                Cancelar
            </a>
        </div>
    </form>
</div>
