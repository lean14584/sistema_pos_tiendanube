<form wire:submit="save" class="space-y-5 max-w-xl">
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Logo</label>
        <div class="flex items-center gap-4">
            @if ($logo)
                <img src="{{ $logo->temporaryUrl() }}" class="w-16 h-16 rounded-lg object-contain border border-gray-200 dark:border-gray-800 bg-white">
            @elseif (isset($sucursal) && $sucursal->logo_path)
                <img src="{{ $sucursal->logo_url }}" class="w-16 h-16 rounded-lg object-contain border border-gray-200 dark:border-gray-800 bg-white">
            @else
                <div class="w-16 h-16 rounded-lg border border-dashed border-gray-300 dark:border-gray-700 flex items-center justify-center text-gray-300 dark:text-gray-700">
                    <x-heroicon-o-building-storefront class="w-6 h-6" />
                </div>
            @endif
            <input type="file" wire:model="logo" accept="image/*" class="text-sm text-gray-600 dark:text-gray-400 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 dark:file:bg-gray-800 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-gray-700 dark:file:text-gray-300 hover:file:bg-gray-200 dark:hover:file:bg-gray-700">
        </div>
        @error('logo') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre *</label>
        <input
            type="text"
            wire:model="name"
            required
            class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors"
            placeholder="Sucursal Centro"
        >
        @error('name') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Razón social de esta sucursal *</label>
        <input
            type="text"
            wire:model="razon_social"
            required
            class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors"
        >
        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Es la que se usa para facturar desde esta sucursal en particular. El nombre general de la empresa se carga en Configuración → Datos de la Empresa.</p>
        @error('razon_social') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
    </div>

    @unless (isset($sucursal))
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Punto de venta (ARCA) *</label>
            <input
                type="number" min="1" max="9999"
                wire:model="punto_venta"
                required
                class="w-full max-w-[10rem] rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors"
            >
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Tiene que ser un punto de venta ya habilitado en ARCA para esta empresa, distinto del de cualquier otra sucursal. Se pueden agregar más después de crear la sucursal.</p>
            @error('punto_venta') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
        </div>
    @endunless

    <div>
        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="checkbox" wire:model="active" class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500">
            Activa
        </label>
    </div>

    @isset($sucursal)
        <div class="border-t border-gray-200 dark:border-gray-800 pt-5">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">Puntos de venta (ARCA)</h2>
            <p class="text-xs text-gray-400 dark:text-gray-500 mb-4">Esta sucursal puede facturar desde cualquiera de estos puntos de venta — se elige al momento de facturar (si hay uno solo, se usa directo, sin preguntar).</p>

            @error('puntosVenta') <p class="text-sm text-red-600 dark:text-red-400 mb-3">{{ $message }}</p> @enderror

            <div class="space-y-2 mb-4">
                @foreach ($this->puntosVenta() as $pv)
                    <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 dark:border-gray-800 px-3 py-2">
                        <div class="text-sm">
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ str_pad($pv->numero, 4, '0', STR_PAD_LEFT) }}</span>
                            @if ($pv->nombre)
                                <span class="text-gray-500 dark:text-gray-400">— {{ $pv->nombre }}</span>
                            @endif
                            @unless ($pv->active)
                                <span class="ml-2 text-xs text-gray-400 dark:text-gray-600">(inactivo)</span>
                            @endunless
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="togglePuntoVenta({{ $pv->id }})" class="text-xs font-medium text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400">
                                {{ $pv->active ? 'Desactivar' : 'Activar' }}
                            </button>
                            <button
                                type="button"
                                x-on:click="confirmThen('¿Eliminar el punto de venta {{ str_pad($pv->numero, 4, '0', STR_PAD_LEFT) }}?', () => $wire.eliminarPuntoVenta({{ $pv->id }}))"
                                class="text-xs font-medium text-red-500 hover:text-red-700 dark:hover:text-red-400"
                            >
                                Eliminar
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex items-end gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Número</label>
                    <input type="number" min="1" max="9999" wire:model="nuevoPuntoVentaNumero" placeholder="0002"
                        class="w-28 rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    @error('nuevoPuntoVentaNumero') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="flex-1">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre (opcional)</label>
                    <input type="text" wire:model="nuevoPuntoVentaNombre" placeholder="Online, Mostrador..."
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>
                <button type="button" wire:click="agregarPuntoVenta"
                    class="rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 shadow-sm">
                    Agregar
                </button>
            </div>
        </div>

        <div class="border-t border-gray-200 dark:border-gray-800 pt-5">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">Mercado Pago</h2>
            <p class="text-xs text-gray-400 dark:text-gray-500 mb-4">Opcional: cuenta de Mercado Pago propia de esta sucursal para el cobro con QR. Si se deja vacío, se usa la cuenta global configurada en el sistema.</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Access Token
                        @if ($this->mpTokenCargado())
                            <span class="text-emerald-600 dark:text-emerald-400 font-normal">(cargado)</span>
                        @endif
                    </label>
                    <input type="password" wire:model="mp_access_token" placeholder="{{ $this->mpTokenCargado() ? 'Dejalo vacío para no cambiarlo' : 'APP_USR-...' }}"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    @error('mp_access_token') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">External store ID</label>
                    <input type="text" wire:model="mp_store_external_id" placeholder="SUC001"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    @error('mp_store_external_id') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">External POS ID</label>
                    <input type="text" wire:model="mp_pos_external_id" placeholder="CAJA001"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    @error('mp_pos_external_id') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre de la sucursal en MP</label>
                    <input type="text" wire:model="mp_store_name" placeholder="{{ $sucursal->name }}"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    @error('mp_store_name') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre de la caja en MP</label>
                    <input type="text" wire:model="mp_pos_name" placeholder="Caja 1"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    @error('mp_pos_name') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
    @endisset

    <div class="flex gap-3 pt-2">
        <button
            type="submit"
            wire:loading.attr="disabled"
            class="rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600 hover:shadow-lg hover:shadow-indigo-600/40 active:scale-[0.98] transition-all disabled:opacity-50"
        >
            {{ $submitLabel }}
        </button>
        <a
            href="{{ route('sucursales.index') }}"
            wire:navigate
            class="rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-400 dark:hover:border-gray-600 hover:shadow-md active:scale-[0.98] transition-all"
        >
            Cancelar
        </a>
    </div>
</form>
