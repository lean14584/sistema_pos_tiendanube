<div class="p-8 max-w-5xl mx-auto">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100 mb-2">Datos de la empresa</h1>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">
        Estos datos se usan para determinar el tipo de comprobante (Factura A/B/C) y se envían a AFIP al emitir cada factura.
    </p>

    @if (session('status'))
        <div class="mb-6 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 px-4 py-3 text-sm">
            {{ session('status') }}
        </div>
    @endif

    <form wire:submit="save" class="space-y-5 max-w-xl" enctype="multipart/form-data">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Logo</label>
            <div class="flex items-center gap-4">
                @if ($logo)
                    <img src="{{ $logo->temporaryUrl() }}" class="w-16 h-16 rounded-lg object-contain border border-gray-200 dark:border-gray-800 bg-white">
                @elseif ($company->logo_path)
                    <img src="{{ asset('storage/'.$company->logo_path) }}" class="w-16 h-16 rounded-lg object-contain border border-gray-200 dark:border-gray-800 bg-white">
                @else
                    <div class="w-16 h-16 rounded-lg border border-dashed border-gray-300 dark:border-gray-700 flex items-center justify-center text-gray-300 dark:text-gray-600">
                        <x-heroicon-o-photo class="w-6 h-6" />
                    </div>
                @endif
                <input type="file" wire:model="logo" accept="image/*" class="text-sm text-gray-600 dark:text-gray-400 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 dark:file:bg-gray-800 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-gray-700 dark:file:text-gray-300 hover:file:bg-gray-200 dark:hover:file:bg-gray-700">
            </div>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Se muestra en el encabezado de las facturas en PDF. Máximo 2&nbsp;MB.</p>
            @error('logo') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">CUIT *</label>
                <input
                    type="text"
                    wire:model="cuit"
                    required
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors"
                    placeholder="20111111112"
                >
                @error('cuit') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Punto de venta *</label>
                <input
                    type="number"
                    wire:model="punto_venta"
                    required
                    min="1"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors"
                >
                @error('punto_venta') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Razón social *</label>
            <input
                type="text"
                wire:model="razon_social"
                required
                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors"
                placeholder="Mi Empresa S.A."
            >
            @error('razon_social') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre de fantasía</label>
            <input
                type="text"
                wire:model="nombre_fantasia"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors"
            >
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Domicilio</label>
            <textarea
                wire:model="domicilio"
                rows="2"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors"
            ></textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Condición frente al IVA *</label>
            <select
                wire:model="condicion_iva"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors"
            >
                @foreach ($condicionIvaOptions as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>
            @error('condicion_iva') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                Determina si tus facturas salen como A, B o C. Cambiarla afecta a las próximas facturas que emitas, nunca a las ya emitidas.
            </p>
        </div>

        <div class="pt-6 mt-2 border-t border-gray-200 dark:border-gray-800">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Facturación electrónica (AFIP)</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 mb-4">
                Habilitá los tipos de comprobante que emitís y cargá el certificado y la clave privada que te da AFIP.
            </p>

            {{-- Toggles A / B --}}
            <div class="space-y-3">
                <label class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 dark:border-gray-800 px-4 py-3 cursor-pointer">
                    <span>
                        <span class="block text-sm font-medium text-gray-800 dark:text-gray-200">Factura A</span>
                        <span class="block text-xs text-gray-400 dark:text-gray-500">Para clientes responsables inscriptos (discrimina IVA).</span>
                    </span>
                    <input type="checkbox" wire:model="factura_a_habilitada" class="w-5 h-5 rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                </label>

                <label class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 dark:border-gray-800 px-4 py-3 cursor-pointer">
                    <span>
                        <span class="block text-sm font-medium text-gray-800 dark:text-gray-200">Factura B</span>
                        <span class="block text-xs text-gray-400 dark:text-gray-500">Para consumidor final y monotributistas (IVA incluido).</span>
                    </span>
                    <input type="checkbox" wire:model="factura_b_habilitada" class="w-5 h-5 rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                </label>
            </div>

            {{-- Certificado y clave --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Certificado (.crt)</label>
                        @if ($cert)
                            <span class="text-xs text-indigo-600 dark:text-indigo-400">Nuevo: {{ $cert->getClientOriginalName() }}</span>
                        @elseif ($certCargado)
                            <span class="inline-flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-400"><x-heroicon-o-check-circle class="w-4 h-4" /> Cargado</span>
                        @else
                            <span class="text-xs text-amber-600 dark:text-amber-400">Falta cargar</span>
                        @endif
                    </div>
                    <input type="file" wire:model="cert" accept=".crt,.pem,.cer" class="w-full text-sm text-gray-600 dark:text-gray-400 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 dark:file:bg-gray-800 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-gray-700 dark:file:text-gray-300 hover:file:bg-gray-200 dark:hover:file:bg-gray-700">
                    @error('cert') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Clave privada (.key)</label>
                        @if ($key)
                            <span class="text-xs text-indigo-600 dark:text-indigo-400">Nueva: {{ $key->getClientOriginalName() }}</span>
                        @elseif ($keyCargada)
                            <span class="inline-flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-400"><x-heroicon-o-check-circle class="w-4 h-4" /> Cargada</span>
                        @else
                            <span class="text-xs text-amber-600 dark:text-amber-400">Falta cargar</span>
                        @endif
                    </div>
                    <input type="file" wire:model="key" accept=".key,.pem" class="w-full text-sm text-gray-600 dark:text-gray-400 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 dark:file:bg-gray-800 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-gray-700 dark:file:text-gray-300 hover:file:bg-gray-200 dark:hover:file:bg-gray-700">
                    @error('key') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-2 flex items-start gap-1.5">
                <x-heroicon-o-lock-closed class="w-4 h-4 shrink-0 mt-0.5" />
                Los archivos se guardan en el servidor fuera de la carpeta pública. No se muestran ni se pueden descargar desde acá.
            </p>
        </div>

        <div class="flex gap-3 pt-2">
            <button
                type="submit"
                class="rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600 hover:shadow-lg hover:shadow-indigo-600/40 active:scale-[0.98] transition-all"
            >
                Guardar cambios
            </button>
        </div>
    </form>
</div>
