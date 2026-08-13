<div class="p-8 max-w-3xl mx-auto">
    <x-page-header title="Respaldo" subtitle="Descargá una copia completa del sistema para guardarla en un lugar seguro." icon="circle-stack" />

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        @foreach ($conteos as $c)
            <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-4">
                <x-dynamic-component :component="'heroicon-o-'.$c['icon']" class="w-5 h-5 text-indigo-500 mb-2" />
                <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $c['value'] }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $c['label'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-6">
        <div class="flex items-start gap-4">
            <div class="w-11 h-11 shrink-0 rounded-lg bg-indigo-500/10 flex items-center justify-center">
                <x-heroicon-o-circle-stack class="w-6 h-6 text-indigo-500" />
            </div>
            <div class="flex-1 min-w-0">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Respaldo completo (.zip)</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Incluye la <strong>base de datos completa</strong> ({{ $dbSize }}) y los <strong>archivos subidos</strong>
                    (logo, certificados, etc.). Se genera una copia consistente aunque el sistema esté en uso.
                </p>

                <a
                    href="{{ route('backups.download') }}"
                    class="mt-4 inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600 hover:shadow-lg hover:shadow-indigo-600/40 active:scale-[0.98] transition-all"
                >
                    <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                    Descargar respaldo ahora
                </a>
            </div>
        </div>

        <div class="mt-5 flex items-start gap-2 rounded-lg bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 px-4 py-3">
            <x-heroicon-o-exclamation-triangle class="w-5 h-5 shrink-0 text-amber-600 dark:text-amber-400" />
            <p class="text-sm text-amber-800 dark:text-amber-300">
                Guardá el archivo en <strong>otro dispositivo</strong> (pendrive, disco externo o la nube). Un respaldo
                que queda en la misma computadora no sirve si el equipo falla. Hacelo con frecuencia.
            </p>
        </div>
    </div>

    {{-- Respaldo automático --}}
    <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-6 mt-6">
        <div class="flex items-center gap-2 mb-3">
            <x-heroicon-o-clock class="w-5 h-5 text-indigo-500" />
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Respaldo automático</h2>
        </div>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Todos los días a las <strong>{{ $autoHora }}</strong> se genera un respaldo solo y se guarda en
            <code class="text-xs bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">{{ $autoPath }}</code>,
            conservando los últimos <strong>{{ $autoKeep }}</strong>.
            @if ($copyTo)
                También se copia a <code class="text-xs bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">{{ $copyTo }}</code>.
            @endif
        </p>
        <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">
            Requiere que el programador de tareas esté activo (ver la nota al pie). También podés generarlo a mano cuando quieras con el botón de arriba.
        </p>

        @if (count($respaldos) > 0)
            <div class="mt-4 border border-gray-200 dark:border-gray-800 rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800/50 text-left text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-2 font-medium">Archivo</th>
                            <th class="px-4 py-2 font-medium">Fecha</th>
                            <th class="px-4 py-2 font-medium text-right">Tamaño</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($respaldos as $r)
                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300 font-mono text-xs">{{ $r['nombre'] }}</td>
                                <td class="px-4 py-2 text-gray-600 dark:text-gray-400">{{ $r['fecha'] }}</td>
                                <td class="px-4 py-2 text-right text-gray-600 dark:text-gray-400">{{ $r['tamano'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-gray-400 dark:text-gray-500 mt-3">Todavía no hay respaldos automáticos guardados.</p>
        @endif
    </div>
</div>
