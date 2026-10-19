@php
    $inputClass = 'w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-sky-400 transition';
    $sectionTitle = 'text-xs font-bold text-sky-700 dark:text-sky-400 uppercase tracking-wider';
    $sectionDivider = 'border-t border-sky-200/70 dark:border-gray-800 pt-5 mt-5';
    $label = 'block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1';
    $fileInputClass = 'w-full text-sm text-gray-600 dark:text-gray-400 file:mr-3 file:rounded-lg file:border-0 file:bg-sky-50 dark:file:bg-sky-500/10 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-sky-700 dark:file:text-sky-300 hover:file:bg-sky-100 dark:hover:file:bg-sky-500/20';

    // $tabs viene de CompanySettings\Edit::tabs() (única fuente de verdad,
    // compartida con primerTabConError() para el evento que cambia de
    // pestaña en save()).
    $tabsConError = collect($tabs)->filter(fn ($tab) => collect($tab['fields'])->contains(fn ($field) => $errors->has($field)))->keys();
@endphp

<div
    class="p-8 max-w-6xl mx-auto"
    x-data="{ tab: '{{ $tabsConError->first() ?? 'general' }}' }"
    x-on:company-settings-tab-con-error.window="tab = $event.detail.tab"
>
    <x-page-header title="Datos de la empresa" subtitle="Estos datos se usan para determinar el tipo de comprobante (Factura A/B/C) y se envían a ARCA al emitir cada factura." icon="building-office" />

    @if ($tabsConError->isNotEmpty())
        <div class="mb-4 flex flex-wrap items-center gap-2 rounded-xl border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950/40 px-4 py-3 text-sm text-red-700 dark:text-red-300">
            <x-heroicon-o-exclamation-circle class="w-5 h-5 shrink-0" />
            <span>Hay errores en:</span>
            @foreach ($tabsConError as $tabKey)
                <button type="button" x-on:click="tab = '{{ $tabKey }}'" class="font-semibold underline hover:no-underline">{{ $tabs[$tabKey]['label'] }}</button>
            @endforeach
        </div>
    @endif

    <form wire:submit="save" class="max-w-5xl" enctype="multipart/form-data">
        <div class="rounded-2xl border border-sky-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">

            {{-- Navegación de pestañas: estilo subrayado minimalista (mismo
                 patrón que Stripe/Linear/GitHub en configuración) — sin caja
                 de color, solo un borde inferior de acento en la activa. --}}
            <div class="border-b border-sky-200 dark:border-gray-800 px-5 sm:px-7">
                <nav class="-mb-px flex flex-wrap gap-5 sm:gap-6">
                    @foreach ($tabs as $tabSlug => $tabInfo)
                        <button
                            type="button"
                            x-on:click="tab = '{{ $tabSlug }}'"
                            :class="tab === '{{ $tabSlug }}'
                                ? 'border-sky-600 text-sky-600 dark:text-sky-400'
                                : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 dark:hover:border-gray-700'"
                            class="relative inline-flex items-center gap-1.5 border-b-2 py-3 text-sm font-medium transition-colors"
                        >
                            <x-dynamic-component :component="'heroicon-o-' . $tabInfo['icon']" class="w-4 h-4" />
                            {{ $tabInfo['label'] }}
                            @if ($tabsConError->contains($tabSlug))
                                <span class="absolute -top-0.5 -right-2 w-2 h-2 rounded-full bg-red-500"></span>
                            @endif
                        </button>
                    @endforeach
                </nav>
            </div>

            <div class="p-5 sm:p-7">

                {{-- Pestaña: Datos generales --}}
                <div x-show="tab === 'general'" x-cloak class="space-y-4">
                    <div>
                        <label class="{{ $label }}">Logo</label>
                        <div class="flex items-center gap-4">
                            @if ($logo)
                                <img src="{{ $logo->temporaryUrl() }}" alt="Logo de la empresa" class="w-16 h-16 rounded-xl object-contain border border-gray-200 dark:border-gray-800 bg-white">
                            @elseif ($company->logo_path)
                                <img src="{{ asset('storage/'.$company->logo_path) }}" alt="Logo de la empresa" class="w-16 h-16 rounded-xl object-contain border border-gray-200 dark:border-gray-800 bg-white">
                            @else
                                <div class="w-16 h-16 rounded-xl border border-dashed border-gray-300 dark:border-gray-700 flex items-center justify-center text-gray-300 dark:text-gray-600">
                                    <x-heroicon-o-photo class="w-6 h-6" />
                                </div>
                            @endif
                            <input type="file" wire:model="logo" accept="image/*" class="{{ $fileInputClass }}">
                        </div>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Se muestra en el encabezado de las facturas en PDF. Máximo 2&nbsp;MB.</p>
                        @error('logo') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="{{ $label }}">CUIT</label>
                            <input type="text" wire:model="cuit" class="{{ $inputClass }}" placeholder="20111111112">
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Sin esto no vas a poder facturar con CAE de ARCA, pero podés guardar el resto de los datos igual.</p>
                            @error('cuit') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}">Condición frente al IVA *</label>
                            <select wire:model.live="condicion_iva" class="{{ $inputClass }}">
                                @foreach ($condicionIvaOptions as $option)
                                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                                @endforeach
                            </select>
                            @error('condicion_iva') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Determina los tipos de comprobante — se elige en la pestaña Facturación ARCA.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="{{ $label }}">Razón social *</label>
                            <input type="text" wire:model="razon_social" required class="{{ $inputClass }}" placeholder="Mi Empresa S.A.">
                            @error('razon_social') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}">Nombre de fantasía</label>
                            <input type="text" wire:model="nombre_fantasia" class="{{ $inputClass }}">
                        </div>
                    </div>

                    <div>
                        <label class="{{ $label }}">Domicilio</label>
                        <textarea wire:model="domicilio" rows="2" class="{{ $inputClass }}"></textarea>
                    </div>
                </div>

                {{-- Pestaña: Facturación electrónica (ARCA) --}}
                <div x-show="tab === 'arca'" x-cloak class="space-y-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Cargá el certificado y la clave privada que te da ARCA. Los tipos de comprobante habilitados salen automáticamente de la condición ante IVA (pestaña Datos generales).
                    </p>

                    @if(in_array($condicion_iva, ['monotributista', 'exento']))
                        <div class="flex items-center gap-3 rounded-xl border border-emerald-200 dark:border-emerald-900 bg-emerald-50 dark:bg-emerald-950/40 px-4 py-3">
                            <x-heroicon-o-check-circle class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                            <span>
                                <span class="block text-sm font-medium text-gray-800 dark:text-gray-200">Factura C habilitada</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">No discrimina IVA — es el único comprobante que puede emitir una empresa {{ \App\Enums\CondicionIva::from($condicion_iva)->label() }}.</span>
                            </span>
                        </div>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 px-4 py-3 cursor-pointer">
                                <span>
                                    <span class="block text-sm font-medium text-gray-800 dark:text-gray-200">Factura A</span>
                                    <span class="block text-xs text-gray-400 dark:text-gray-500">Responsables inscriptos (discrimina IVA).</span>
                                </span>
                                <input type="checkbox" wire:model="factura_a_habilitada" class="w-5 h-5 rounded border-gray-300 dark:border-gray-600 text-sky-600 focus:ring-sky-500">
                            </label>
                            <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 px-4 py-3 cursor-pointer">
                                <span>
                                    <span class="block text-sm font-medium text-gray-800 dark:text-gray-200">Factura B</span>
                                    <span class="block text-xs text-gray-400 dark:text-gray-500">Consumidor final (IVA incluido).</span>
                                </span>
                                <input type="checkbox" wire:model="factura_b_habilitada" class="w-5 h-5 rounded border-gray-300 dark:border-gray-600 text-sky-600 focus:ring-sky-500">
                            </label>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 {{ $sectionDivider }}">
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Certificado (.crt)</label>
                                @if ($cert)
                                    <span class="text-xs text-sky-700 dark:text-sky-400">Nuevo: {{ $cert->getClientOriginalName() }}</span>
                                @elseif ($certCargado)
                                    <span class="inline-flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-400"><x-heroicon-o-check-circle class="w-4 h-4" /> Cargado</span>
                                @else
                                    <span class="text-xs text-amber-600 dark:text-amber-400">Falta cargar</span>
                                @endif
                            </div>
                            <input type="file" wire:model="cert" accept=".crt,.pem,.cer" class="{{ $fileInputClass }}">
                            @error('cert') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Clave privada (.key)</label>
                                @if ($key)
                                    <span class="text-xs text-sky-700 dark:text-sky-400">Nueva: {{ $key->getClientOriginalName() }}</span>
                                @elseif ($keyCargada)
                                    <span class="inline-flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-400"><x-heroicon-o-check-circle class="w-4 h-4" /> Cargada</span>
                                @else
                                    <span class="text-xs text-amber-600 dark:text-amber-400">Falta cargar</span>
                                @endif
                            </div>
                            <input type="file" wire:model="key" accept=".key,.pem" class="{{ $fileInputClass }}">
                            @error('key') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 dark:text-gray-500 flex items-start gap-1.5">
                        <x-heroicon-o-lock-closed class="w-4 h-4 shrink-0 mt-0.5" />
                        Los archivos se guardan en el servidor fuera de la carpeta pública. No se muestran ni se pueden descargar desde acá.
                    </p>
                </div>

                @if (! config('features.multisucursal'))
                    {{-- Pestaña: Sucursal (única) --}}
                    <div x-show="tab === 'sucursal'" x-cloak class="space-y-4">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Como el sistema es de una sola sucursal, estos datos se cargan directo acá en vez de en una pantalla aparte.
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="{{ $label }}">Nombre *</label>
                                <input type="text" wire:model="sucursal_name" required class="{{ $inputClass }}" placeholder="Principal">
                                @error('sucursal_name') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="{{ $label }}">Razón social de la sucursal *</label>
                                <input type="text" wire:model="sucursal_razon_social" required class="{{ $inputClass }}">
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Es la que se usa para facturar. El nombre general de la empresa se carga en la pestaña Datos generales.</p>
                                @error('sucursal_razon_social') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" wire:model="sucursal_active" class="rounded border-gray-300 dark:border-gray-700 text-sky-600 focus:ring-sky-500">
                            Activa
                        </label>

                        <div class="space-y-3 {{ $sectionDivider }}">
                            <div>
                                <h3 class="{{ $sectionTitle }}">Puntos de venta (ARCA)</h3>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Se elige al momento de facturar (si hay uno solo, se usa directo, sin preguntar).</p>
                            </div>

                            @error('puntosVenta') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                            <div class="space-y-2">
                                @foreach ($puntosVenta as $pv)
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
                                    <input type="text" wire:model="nuevoPuntoVentaNombre" placeholder="Online, Mostrador..." class="{{ $inputClass }}">
                                </div>
                                <button type="button" wire:click="agregarPuntoVenta"
                                    wire:loading.attr="disabled"
                                    wire:target="agregarPuntoVenta"
                                    class="rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 shadow-sm disabled:opacity-50">
                                    Agregar
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Pestaña: Mercado Pago --}}
                    <div x-show="tab === 'mercadopago'" x-cloak class="space-y-4">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Opcional: credenciales de Mercado Pago para el cobro con QR.
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label class="{{ $label }}">
                                    Access Token
                                    @if ($mpTokenCargado)
                                        <span class="text-emerald-600 dark:text-emerald-400 font-normal">(cargado)</span>
                                    @endif
                                </label>
                                <input type="password" wire:model="mp_access_token" placeholder="{{ $mpTokenCargado ? 'Dejalo vacío para no cambiarlo' : 'APP_USR-...' }}" class="{{ $inputClass }}">
                                @error('mp_access_token') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="{{ $label }}">
                                    Webhook Secret
                                    @if ($mpWebhookSecretCargado)
                                        <span class="text-emerald-600 dark:text-emerald-400 font-normal">(cargado)</span>
                                    @endif
                                </label>
                                <input type="password" wire:model="mp_webhook_secret" placeholder="{{ $mpWebhookSecretCargado ? 'Dejalo vacío para no cambiarlo' : 'Panel de MP → Tus integraciones → Webhooks' }}" class="{{ $inputClass }}">
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Para validar que las notificaciones de pago vienen de Mercado Pago. Sin esto, el cobro se detecta igual, un poco más lento (polling).</p>
                                @error('mp_webhook_secret') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="{{ $label }}">External store ID</label>
                                <input type="text" wire:model="mp_store_external_id" placeholder="SUC001" class="{{ $inputClass }}">
                                @error('mp_store_external_id') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="{{ $label }}">External POS ID</label>
                                <input type="text" wire:model="mp_pos_external_id" placeholder="CAJA001" class="{{ $inputClass }}">
                                @error('mp_pos_external_id') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="{{ $label }}">Nombre de la sucursal en MP</label>
                                <input type="text" wire:model="mp_store_name" placeholder="{{ $sucursal_name }}" class="{{ $inputClass }}">
                                @error('mp_store_name') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="{{ $label }}">Nombre de la caja en MP</label>
                                <input type="text" wire:model="mp_pos_name" placeholder="Caja 1" class="{{ $inputClass }}">
                                @error('mp_pos_name') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                @endif

                @if (config('features.sell_by_weight'))
                    {{-- Pestaña: Código de barras de balanza --}}
                    <div x-show="tab === 'balanza'" x-cloak class="space-y-4">
                        <div>
                            <h3 class="{{ $sectionTitle }}">Código de barras de balanza</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                Para productos que se venden por peso (fiambres, quesos, frutas). El código EAN-13 impreso por la balanza codifica el peso en gramos; el sistema calcula el precio usando el precio por kilo cargado en cada producto. Los tamaños de cada tramo dependen de cómo esté programada tu balanza.
                            </p>
                        </div>

                        <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 px-4 py-3 cursor-pointer">
                            <span>
                                <span class="block text-sm font-medium text-gray-800 dark:text-gray-200">Activar lectura de códigos de balanza</span>
                                <span class="block text-xs text-gray-400 dark:text-gray-500">Sin activar, el POS solo busca por SKU/nombre exacto (como siempre).</span>
                            </span>
                            <input type="checkbox" wire:model.live="barcode_scale_enabled" class="w-5 h-5 rounded border-gray-300 dark:border-gray-600 text-sky-600 focus:ring-sky-500">
                        </label>

                        @if ($barcode_scale_enabled)
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label class="{{ $label }}">Prefijo *</label>
                                    <input type="text" wire:model="barcode_scale_prefix" maxlength="4" class="{{ $inputClass }}" placeholder="20">
                                    @error('barcode_scale_prefix') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="{{ $label }}">Dígitos de código *</label>
                                    <input type="number" min="1" max="9" wire:model="barcode_scale_code_digits" class="{{ $inputClass }}">
                                    @error('barcode_scale_code_digits') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="{{ $label }}">Dígitos de peso *</label>
                                    <input type="number" min="1" max="9" wire:model="barcode_scale_weight_digits" class="{{ $inputClass }}">
                                    @error('barcode_scale_weight_digits') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <p class="text-xs text-gray-400 dark:text-gray-500">
                                Prefijo + dígitos de código + dígitos de peso + 1 (dígito verificador) tienen que sumar 13 en total. Formatos típicos en Argentina: 2-5-5 o 2-4-6.
                            </p>
                            <p class="text-xs text-gray-400 dark:text-gray-500">
                                En cada producto que se venda por peso, marcá "Se vende por peso (kg)" y cargá como código (SKU) el número de {{ (int) $barcode_scale_code_digits ?: '…' }} dígitos programado en la balanza para ese producto.
                            </p>
                        @endif
                    </div>
                @endif

                {{-- Pestaña: Descuentos por medio de pago --}}
                <div x-show="tab === 'descuentos'" x-cloak class="space-y-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        El precio de lista de los productos es el que se factura con tarjeta (equivale a "crédito"). Acá podés definir un descuento adicional para el cliente que paga de contado en efectivo o transferencia. Dejá en 0 el que no uses.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="{{ $label }}">Descuento en efectivo (%)</label>
                            <input type="number" min="0" max="100" step="0.01" wire:model="descuento_efectivo_pct" class="{{ $inputClass }}" placeholder="15">
                            @error('descuento_efectivo_pct') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}">Descuento en transferencia (%)</label>
                            <input type="number" min="0" max="100" step="0.01" wire:model="descuento_transferencia_pct" class="{{ $inputClass }}" placeholder="10">
                            @error('descuento_transferencia_pct') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 dark:text-gray-500">
                        Si en el POS se cobra con varios medios de pago a la vez, el descuento se aplica solo a la parte cubierta por efectivo/transferencia, y solo cuando la venta queda pagada por completo en el momento (no aplica si queda saldo en cuenta corriente).
                    </p>
                </div>

                {{-- Pestaña: Apariencia --}}
                <div
                    x-show="tab === 'apariencia'" x-cloak
                    class="space-y-4"
                    x-data="{ primary: @entangle('theme_primary_color'), secondary: @entangle('theme_secondary_color') }"
                >
                    <div class="flex items-start justify-between gap-4">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Los dos colores de marca de todo el sistema: el principal (botones, enlaces, estados activos) y el secundario (foco de los campos, acentos). Dejá en blanco para usar los colores de fábrica.
                        </p>
                        <button
                            type="button"
                            x-on:click="primary = ''; secondary = ''"
                            class="shrink-0 text-xs font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 underline"
                        >
                            Restablecer
                        </button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="{{ $label }}">Color principal</label>
                            <div class="flex items-center gap-2">
                                <input type="color" x-model="primary" class="h-10 w-12 shrink-0 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer bg-transparent p-0.5">
                                <input type="text" x-model="primary" maxlength="7" placeholder="#4f46e5" class="{{ $inputClass }} font-mono uppercase">
                            </div>
                            @error('theme_primary_color') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}">Color secundario</label>
                            <div class="flex items-center gap-2">
                                <input type="color" x-model="secondary" class="h-10 w-12 shrink-0 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer bg-transparent p-0.5">
                                <input type="text" x-model="secondary" maxlength="7" placeholder="#0ea5e9" class="{{ $inputClass }} font-mono uppercase">
                            </div>
                            @error('theme_secondary_color') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 px-4 py-3">
                        <span class="text-xs text-gray-400 dark:text-gray-500">Vista previa:</span>
                        <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-semibold text-white shadow-sm" :style="`background-color: ${primary || '#4f46e5'}`">Botón</button>
                        <span class="text-sm font-medium underline" :style="`color: ${secondary || '#0ea5e9'}`">Enlace</span>
                    </div>
                    <p class="text-xs text-gray-400 dark:text-gray-500">
                        Esta vista previa es solo aproximada (no genera los tintes claros/oscuros de cada tono) — el resultado final en todo el sistema se ve al guardar.
                    </p>
                </div>
            </div>

            <div class="flex gap-3 border-t border-sky-200/70 dark:border-gray-800 px-5 sm:px-7 py-5">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="rounded-lg bg-sky-600 hover:bg-sky-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm disabled:opacity-50 transition-all"
                >
                    Guardar cambios
                </button>
            </div>
        </div>
    </form>
</div>
