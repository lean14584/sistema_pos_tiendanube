<div class="p-8 max-w-3xl mx-auto">
    <div class="mb-8">
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Respaldo</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Descargá una copia completa del sistema para guardarla en un lugar seguro.</p>
    </div>

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
</div>
