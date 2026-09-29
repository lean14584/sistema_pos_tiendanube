@php
    $inputClass = 'w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-sky-400 transition';
    $sectionTitle = 'text-xs font-bold text-sky-700 dark:text-sky-400 uppercase tracking-wider';
    $sectionDivider = 'border-t border-sky-200/70 dark:border-gray-800 pt-5 mt-5';
    $label = 'block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1';
    $fileInputClass = 'w-full text-sm text-gray-600 dark:text-gray-400 file:mr-3 file:rounded-lg file:border-0 file:bg-sky-50 dark:file:bg-sky-500/10 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-sky-700 dark:file:text-sky-300 hover:file:bg-sky-100 dark:hover:file:bg-sky-500/20';
@endphp

<div class="p-8 max-w-6xl mx-auto">
    <x-page-header title="Datos de la empresa" subtitle="Estos datos se usan para determinar el tipo de comprobante (Factura A/B/C) y se envían a ARCA al emitir cada factura." icon="building-office" />

    <form wire:submit="save" class="max-w-5xl" enctype="multipart/form-data">
        <div class="rounded-2xl border border-sky-200 dark:border-gray-800 bg-sky-100/70 dark:bg-gray-900 shadow-sm p-5 sm:p-7">

            {{-- Sección: Datos generales --}}
            <div class="space-y-4">
                <h3 class="{{ $sectionTitle }}">Datos generales</h3>

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

                <div>
                    <label class="{{ $label }}">CUIT</label>
                    <input
                        type="text"
                        wire:model="cuit"
                        class="{{ $inputClass }} sm:max-w-xs"
                        placeholder="20111111112"
                    >
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Sin esto no vas a poder facturar con CAE de ARCA, pero podés guardar el resto de los datos igual.</p>
                    @error('cuit') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $label }}">Razón social *</label>
                        <input
                            type="text"
                            wire:model="razon_social"
                            required
                            class="{{ $inputClass }}"
                            placeholder="Mi Empresa S.A."
                        >
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

                <div>
                    <label class="{{ $label }}">Condición frente al IVA *</label>
                    <select wire:model.live="condicion_iva" class="{{ $inputClass }} sm:max-w-xs">
                        @foreach ($condicionIvaOptions as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                    @error('condicion_iva') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                        Determina si tus facturas salen como A, B o C — se elige solo más abajo según esta condición. Cambiarla afecta a las próximas facturas que emitas, nunca a las ya emitidas.
                    </p>
                </div>
            </div>

            {{-- Sección: Facturación electrónica (ARCA) --}}
            <div class="space-y-4 {{ $sectionDivider }}">
                <div>
                    <h3 class="{{ $sectionTitle }}">Facturación electrónica (ARCA)</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Cargá el certificado y la clave privada que te da ARCA. Los tipos de comprobante habilitados salen automáticamente de la condición ante IVA de arriba.
                    </p>
                </div>

                @if(in_array($condicion_iva, ['monotributista', 'exento']))
                    {{-- Monotributista/Exento: único tipo posible es C, no hay nada para elegir. --}}
                    <div class="flex items-center gap-3 rounded-xl border border-emerald-200 dark:border-emerald-900 bg-emerald-50 dark:bg-emerald-950/40 px-4 py-3">
                        <x-heroicon-o-check-circle class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                        <span>
                            <span class="block text-sm font-medium text-gray-800 dark:text-gray-200">Factura C habilitada</span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">No discrimina IVA — es el único comprobante que puede emitir una empresa {{ \App\Enums\CondicionIva::from($condicion_iva)->label() }}.</span>
                        </span>
                    </div>
                @else
                    {{-- Responsable Inscripto: puede elegir entre A y B según a quién le vende. --}}
                    <div class="space-y-3">
                        <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 px-4 py-3 cursor-pointer">
                            <span>
                                <span class="block text-sm font-medium text-gray-800 dark:text-gray-200">Factura A</span>
                                <span class="block text-xs text-gray-400 dark:text-gray-500">Para clientes responsables inscriptos (discrimina IVA).</span>
                            </span>
                            <input type="checkbox" wire:model="factura_a_habilitada" class="w-5 h-5 rounded border-gray-300 dark:border-gray-600 text-sky-600 focus:ring-sky-500">
                        </label>

                        <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 px-4 py-3 cursor-pointer">
                            <span>
                                <span class="block text-sm font-medium text-gray-800 dark:text-gray-200">Factura B</span>
                                <span class="block text-xs text-gray-400 dark:text-gray-500">Para consumidor final y otros no responsables inscriptos (IVA incluido).</span>
                            </span>
                            <input type="checkbox" wire:model="factura_b_habilitada" class="w-5 h-5 rounded border-gray-300 dark:border-gray-600 text-sky-600 focus:ring-sky-500">
                        </label>
                    </div>
                @endif

                {{-- Certificado y clave --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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

            @if (config('features.sell_by_weight'))
            {{-- Sección: Código de barras de balanza --}}
            <div class="space-y-4 {{ $sectionDivider }}">
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

            {{-- Sección: Descuentos por medio de pago --}}
            <div class="space-y-4 {{ $sectionDivider }}">
                <div>
                    <h3 class="{{ $sectionTitle }}">Descuentos por medio de pago</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        El precio de lista de los productos es el que se factura con tarjeta (equivale a "crédito"). Acá podés definir un descuento adicional para el cliente que paga de contado en efectivo o transferencia. Dejá en 0 el que no uses.
                    </p>
                </div>

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

            <div class="flex gap-3 {{ $sectionDivider }}">
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
