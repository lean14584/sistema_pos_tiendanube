@php
    $inputClass = 'w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors';
@endphp

<form wire:submit="save" class="space-y-5 max-w-xl">
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre *</label>
        <input type="text" wire:model="name" required class="{{ $inputClass }}" placeholder="Juana Pérez">
        @error('name') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Usuario *</label>
            <input type="text" wire:model="username" required class="{{ $inputClass }}" placeholder="jperez">
            @error('username') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Contraseña {{ $isEdit ? '(dejar en blanco para no cambiar)' : '*' }}
            </label>
            <input type="password" wire:model="password" class="{{ $inputClass }}">
            @error('password') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rol</label>
            <select wire:model="role" class="{{ $inputClass }}">
                @foreach ($roles as $r)
                    <option value="{{ $r->value }}">{{ $r->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end pb-2">
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" wire:model="active" class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500">
                Usuario activo
            </label>
        </div>
    </div>

    <div class="flex gap-3 pt-2">
        <button type="submit" class="rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600 hover:shadow-lg hover:shadow-indigo-600/40 active:scale-[0.98] transition-all">
            {{ $submitLabel }}
        </button>
        <a href="{{ route('users.index') }}" wire:navigate class="rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-400 dark:hover:border-gray-600 hover:shadow-md active:scale-[0.98] transition-all">
            Cancelar
        </a>
    </div>
</form>
