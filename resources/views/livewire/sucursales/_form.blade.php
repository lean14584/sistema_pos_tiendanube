@php
    $inputClass = 'w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-sky-400 transition';
    $sectionTitle = 'text-xs font-bold text-sky-700 dark:text-sky-400 uppercase tracking-wider';
    $sectionDivider = 'border-t border-sky-200/70 dark:border-gray-800 pt-5 mt-5';
    $label = 'block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1';
@endphp

<form wire:submit="save" class="max-w-5xl">
    <div class="rounded-2xl border border-sky-100 dark:border-gray-800 bg-sky-50/50 dark:bg-gray-900 shadow-sm p-5 sm:p-7">

        {{-- Logo sacado de este form a propósito: era un campo muerto (ver
             MEJORA en Sucursales\Edit) - no se usa en ningún ticket, factura,
             PDF ni en el kiosco de precios, solo en estas pantallas de
             Sucursales. El logo real del sistema es el de Datos de la Empresa
             (CompanySettings). La columna sucursales.logo_path queda en la
             base sin tocar (bajo riesgo, no se borra por una migración). --}}
        <div class="space-y-4">
            <h3 class="{{ $sectionTitle }}">Datos generales</h3>

            <div>
                <label class="{{ $label }}">Nombre *</label>
                <input
                    type="text"
                    wire:model="name"
                    required
                    class="{{ $inputClass }}"
                    placeholder="Sucursal Centro"
                >
                @error('name') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="{{ $label }}">Razón social de esta sucursal *</label>
                <input
                    type="text"
                    wire:model="razon_social"
                    required
                    class="{{ $inputClass }}"
                >
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Es la que se usa para facturar desde esta sucursal en particular. El nombre general de la empresa se carga en Configuración → Datos de la Empresa.</p>
                @error('razon_social') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            @unless (isset($sucursal))
                <div class="sm:max-w-[10rem]">
                    <label class="{{ $label }}">Punto de venta (ARCA) *</label>
                    <input
                        type="number" min="1" max="9999"
                        wire:model="punto_venta"
                        required
                        class="{{ $inputClass }}"
                    >
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Tiene que ser un punto de venta ya habilitado en ARCA para esta empresa, distinto del de cualquier otra sucursal. Se pueden agregar más después de crear la sucursal.</p>
                    @error('punto_venta') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
            @endunless

            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" wire:model="active" class="rounded border-gray-300 dark:border-gray-700 text-sky-600 focus:ring-sky-500">
                Activa
            </label>
        </div>

        @isset($sucursal)
            {{-- Sección: Puntos de venta (ARCA) --}}
            <div class="space-y-3 {{ $sectionDivider }}">
                <h3 class="{{ $sectionTitle }}">Puntos de venta (ARCA)</h3>
                <p class="text-xs text-gray-400 dark:text-gray-500">Esta sucursal puede facturar desde cualquiera de estos puntos de venta — se elige al momento de facturar (si hay uno solo, se usa directo, sin preguntar).</p>

                @error('puntosVenta') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                <div class="space-y-2">
                    @foreach ($this->puntosVenta() as $pv)
                        <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 px-3 py-2">
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
                                <button type="button" wire:click="togglePuntoVenta({{ $pv->id }})" class="text-xs font-medium text-gray-600 dark:text-gray-400 hover:text-sky-700 dark:hover:text-sky-400">
                                    {{ $pv->active ? 'Desactivar' : 'Activar' }}
                                </button>
                                <button
                                    type="button"
                                    x-on:click="confirmThen('¿Eliminar el punto de venta {{ str_pad($pv->numero, 4, '0', STR_PAD_LEFT) }}?', () => $wire.eliminarPuntoVenta({{ $pv->id }}))"
                                    wire:loading.attr="disabled"
                                    wire:target="eliminarPuntoVenta"
                                    class="text-xs font-medium text-red-500 hover:text-red-700 dark:hover:text-red-400 disabled:opacity-50"
                                >
                                    Eliminar
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex items-end gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Número</label>
                        <input type="number" min="1" max="9999" wire:model="nuevoPuntoVentaNumero" placeholder="0002"
                            class="w-28 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 dark:text-gray-100 px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-sky-400 transition">
                        @error('nuevoPuntoVentaNumero') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex-1">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Nombre (opcional)</label>
                        <input type="text" wire:model="nuevoPuntoVentaNombre" placeholder="Online, Mostrador..."
                            class="{{ $inputClass }}">
                    </div>
                    <button type="button" wire:click="agregarPuntoVenta"
                        wire:loading.attr="disabled"
                        wire:target="agregarPuntoVenta"
                        class="rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 shadow-sm disabled:opacity-50">
                        Agregar
                    </button>
                </div>
            </div>

            {{-- Sección: Mercado Pago --}}
            <div class="space-y-4 {{ $sectionDivider }}">
                <div>
                    <h3 class="{{ $sectionTitle }}">Mercado Pago</h3>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Opcional: cuenta de Mercado Pago propia de esta sucursal para el cobro con QR. Si se deja vacío, se usa la cuenta global configurada en el sistema.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="{{ $label }}">
                            Access Token
                            @if ($this->mpTokenCargado())
                                <span class="text-emerald-600 dark:text-emerald-400 font-normal">(cargado)</span>
                            @endif
                        </label>
                        <input type="password" wire:model="mp_access_token" placeholder="{{ $this->mpTokenCargado() ? 'Dejalo vacío para no cambiarlo' : 'APP_USR-...' }}"
                            class="{{ $inputClass }}">
                        @error('mp_access_token') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="{{ $label }}">
                            Webhook Secret
                            @if ($this->mpWebhookSecretCargado())
                                <span class="text-emerald-600 dark:text-emerald-400 font-normal">(cargado)</span>
                            @endif
                        </label>
                        <input type="password" wire:model="mp_webhook_secret" placeholder="{{ $this->mpWebhookSecretCargado() ? 'Dejalo vacío para no cambiarlo' : 'Panel de MP → Tus integraciones → Webhooks' }}"
                            class="{{ $inputClass }}">
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Para que el sistema pueda validar que las notificaciones de pago realmente vienen de Mercado Pago. Sin esto, el cobro se sigue detectando igual, solo un poco más lento (por polling en vez de al instante).</p>
                        @error('mp_webhook_secret') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">External store ID</label>
                        <input type="text" wire:model="mp_store_external_id" placeholder="SUC001"
                            class="{{ $inputClass }}">
                        @error('mp_store_external_id') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">External POS ID</label>
                        <input type="text" wire:model="mp_pos_external_id" placeholder="CAJA001"
                            class="{{ $inputClass }}">
                        @error('mp_pos_external_id') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">Nombre de la sucursal en MP</label>
                        <input type="text" wire:model="mp_store_name" placeholder="{{ $sucursal->name }}"
                            class="{{ $inputClass }}">
                        @error('mp_store_name') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">Nombre de la caja en MP</label>
                        <input type="text" wire:model="mp_pos_name" placeholder="Caja 1"
                            class="{{ $inputClass }}">
                        @error('mp_pos_name') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        @endisset

        <div class="flex gap-3 {{ $sectionDivider }}">
            <button
                type="submit"
                wire:loading.attr="disabled"
                class="rounded-lg bg-sky-600 hover:bg-sky-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm disabled:opacity-50 transition-all"
            >
                {{ $submitLabel }}
            </button>
            <a
                {{-- sucursales.index no existe si multisucursal está apagado: este
                     partial también lo usa create.blade.php, pero esa pantalla solo
                     es alcanzable CON multisucursal prendido, así que ahí el
                     ternario nunca llega a evaluar $sucursal (no existe en ese contexto). --}}
                href="{{ config('features.multisucursal') ? route('sucursales.index') : route('sucursales.edit', $sucursal) }}"
                wire:navigate
                class="rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-5 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-all"
            >
                Cancelar
            </a>
        </div>
    </div>
</form>
