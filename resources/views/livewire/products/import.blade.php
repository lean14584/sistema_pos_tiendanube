@php $inputClass = 'w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent'; @endphp

<div class="p-8 max-w-5xl mx-auto">
    <a href="{{ route('products.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 mb-6">
        <x-heroicon-o-arrow-left class="w-4 h-4" /> Productos
    </a>
    <x-page-header title="Importar productos desde Excel" subtitle="Subí un archivo .xlsx/.csv, emparejá sus columnas con los campos del sistema, y listo." icon="arrow-up-tray" />

    {{-- Paso 1: subir --}}
    @if ($step === 'subir')
        <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md p-8 text-center">
            <x-heroicon-o-document-arrow-up class="w-10 h-10 mx-auto mb-3 text-gray-300 dark:text-gray-700" />
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Elegí un archivo Excel (.xlsx, .xls) o CSV con tus productos.</p>
            <input type="file" wire:model="archivo" accept=".xlsx,.xls,.csv" class="mx-auto block text-sm text-gray-600 dark:text-gray-300 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-indigo-600 file:text-white file:text-sm file:font-medium hover:file:bg-indigo-500">
            <div wire:loading wire:target="archivo" class="text-xs text-gray-400 mt-3">Leyendo archivo...</div>
            @error('archivo') <p class="text-xs text-red-600 dark:text-red-400 mt-3">{{ $message }}</p> @enderror
        </div>
    @endif

    {{-- Paso 2: mapear --}}
    @if ($step === 'mapear')
        <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md p-5 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-medium text-gray-900 dark:text-gray-100">Emparejar columnas · {{ $totalFilas }} filas encontradas</h2>
                <button wire:click="cancelar" class="text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">Elegir otro archivo</button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3">
                @foreach ($campos as $clave => $campo)
                    <div class="flex items-center gap-3">
                        <label class="w-40 shrink-0 text-sm text-gray-700 dark:text-gray-300">
                            {{ $campo['label'] }}{{ $campo['required'] ? ' *' : '' }}
                        </label>
                        <select wire:model.live="mapeo.{{ $clave }}" class="{{ $inputClass }}">
                            <option value="">— no importar —</option>
                            @foreach ($cabeceras as $indice => $cabecera)
                                @if ($cabecera !== '')
                                    <option value="{{ $indice }}">{{ $cabecera }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>
            @error('mapeo.name') <p class="text-xs text-red-600 dark:text-red-400 mt-3">{{ $message }}</p> @enderror
            @error('mapeo.price') <p class="text-xs text-red-600 dark:text-red-400 mt-3">{{ $message }}</p> @enderror

            <div class="flex gap-3 mt-5">
                <button wire:click="confirmarImportacion" wire:loading.attr="disabled" class="rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md hover:from-indigo-700 hover:to-indigo-600 disabled:opacity-50">
                    Importar {{ $totalFilas }} {{ Str::plural('fila', $totalFilas) }}
                </button>
            </div>
        </div>

        <h3 class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Vista previa (primeras {{ min(5, $totalFilas) }} filas, con el emparejamiento de arriba)</h3>
        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800/50">
                    <tr class="text-left text-gray-500 dark:text-gray-400">
                        @foreach ($campos as $campo)
                            <th class="px-3 py-2 font-medium whitespace-nowrap">{{ $campo['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($previewMapeado as $fila)
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            @foreach ($fila as $valor)
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ $valor === null || $valor === '' ? '—' : $valor }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
    @endif

    {{-- Paso 3: resultado --}}
    @if ($step === 'resultado' && $resultado)
        <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md p-8 text-center">
            <x-heroicon-o-check-circle class="w-10 h-10 mx-auto mb-3 text-emerald-500" />
            <p class="text-lg font-medium text-gray-900 dark:text-gray-100">Importación completa</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                {{ $resultado['creados'] }} {{ Str::plural('producto', $resultado['creados']) }} creado{{ $resultado['creados'] === 1 ? '' : 's' }},
                {{ $resultado['actualizados'] }} actualizado{{ $resultado['actualizados'] === 1 ? '' : 's' }}.
            </p>

            @if (! empty($resultado['omitidos']))
                <div class="mt-4 text-left bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 rounded-lg p-4 text-sm text-amber-800 dark:text-amber-300">
                    <p class="font-medium mb-1">{{ count($resultado['omitidos']) }} fila(s) omitida(s):</p>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach ($resultado['omitidos'] as $motivo)
                            <li>{{ $motivo }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="flex justify-center gap-3 mt-6">
                <a href="{{ route('products.index') }}" wire:navigate class="rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md hover:from-indigo-700 hover:to-indigo-600">Ver productos</a>
                <button wire:click="cancelar" class="rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">Importar otro archivo</button>
            </div>
        </div>
    @endif
</div>
