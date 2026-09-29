@php
    $inputClass = 'w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-sky-400 transition';
    $sectionTitle = 'text-xs font-bold text-sky-700 dark:text-sky-400 uppercase tracking-wider';
    $sectionDivider = 'border-t border-sky-200/70 dark:border-gray-800 pt-5 mt-5';
    $label = 'block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1';
@endphp

<form wire:submit="save" class="max-w-5xl">
    <div class="rounded-2xl border border-sky-200 dark:border-gray-800 bg-sky-100/70 dark:bg-gray-900 shadow-sm p-5 sm:p-7">

        <div class="space-y-4">
            <h3 class="{{ $sectionTitle }}">Datos generales</h3>

            <div>
                <label class="{{ $label }}">Nombre *</label>
                <input type="text" wire:model="name" required class="{{ $inputClass }}" placeholder="Juana Pérez">
                @error('name') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $label }}">Usuario *</label>
                    <input type="text" wire:model="username" required class="{{ $inputClass }}" placeholder="jperez">
                    @error('username') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $label }}">
                        Contraseña {{ $isEdit ? '(dejar en blanco para no cambiar)' : '*' }}
                    </label>
                    <input type="password" wire:model="password" class="{{ $inputClass }}">
                    @error('password') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $label }}">
                        Email <span class="text-gray-400 font-normal">(opcional, para poder recuperar la contraseña)</span>
                    </label>
                    <input type="email" wire:model="email" class="{{ $inputClass }}" placeholder="usuario@ejemplo.com">
                    @error('email') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
                @if ($editingSelf ?? false)
                    <div>
                        <label class="{{ $label }}">
                            Tu contraseña actual <span class="text-gray-400 font-normal">(solo si cambiás la contraseña de arriba)</span>
                        </label>
                        <input type="password" wire:model="current_password" class="{{ $inputClass }}">
                        @error('current_password') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                    </div>
                @endif
            </div>
        </div>

        <div class="space-y-4 {{ $sectionDivider }}">
            <h3 class="{{ $sectionTitle }}">Rol y acceso</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $label }}">Rol</label>
                    <select wire:model.live="role" class="{{ $inputClass }}">
                        @foreach ($roles as $r)
                            <option value="{{ $r->value }}">{{ $r->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end pb-2.5">
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" wire:model="active" class="rounded border-gray-300 dark:border-gray-700 text-sky-600 focus:ring-sky-500">
                        Usuario activo
                    </label>
                </div>
            </div>

            @if ($role !== 'admin' && config('features.multisucursal'))
                <div class="sm:max-w-xs">
                    <label class="{{ $label }}">Sucursal *</label>
                    <select wire:model="sucursal_id" class="{{ $inputClass }}">
                        <option value="">Elegir sucursal...</option>
                        @foreach ($sucursales as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Cajero y vendedor operan en una sola sucursal.</p>
                    @error('sucursal_id') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
            @elseif ($role === 'admin' && config('features.multisucursal'))
                <p class="text-xs text-gray-400 dark:text-gray-500">Un administrador ve y opera en todas las sucursales.</p>
            @endif
        </div>

        <div class="flex gap-3 {{ $sectionDivider }}">
            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="rounded-lg bg-sky-600 hover:bg-sky-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm disabled:opacity-50 transition-all">
                {{ $submitLabel }}
            </button>
            <a href="{{ route('users.index') }}" wire:navigate class="rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-5 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-all">
                Cancelar
            </a>
        </div>
    </div>
</form>
