<div>
@if ($mostrarModal)
    <div x-data="{ open: true }" x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4">
        <div class="fixed inset-0 bg-gray-900/60"></div>

        <div class="relative w-full max-w-sm bg-white dark:bg-gray-900 rounded-2xl shadow-2xl border border-gray-200 dark:border-gray-800 overflow-hidden">
            <div class="px-5 py-4 bg-gradient-to-r from-indigo-600 to-sky-500 flex items-center gap-3">
                <span class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-white/20">
                    <x-heroicon-o-banknotes class="w-5 h-5 text-white" />
                </span>
                <div class="flex-1">
                    <h3 class="text-sm font-semibold text-white uppercase tracking-wide">Servicio mensual</h3>
                </div>
                <button type="button" x-on:click="open = false" class="text-white/80 hover:text-white">
                    <x-heroicon-o-x-mark class="w-5 h-5" />
                </button>
            </div>

            <div class="p-5">
                <div class="flex items-center justify-between rounded-xl bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-100 dark:border-indigo-500/20 px-4 py-3 mb-4">
                    <span class="text-sm text-gray-700 dark:text-gray-200">Cobro sistema mensual</span>
                    <span class="text-lg font-bold text-indigo-700 dark:text-indigo-300">${{ number_format($monto, 0, ',', '.') }}</span>
                </div>

                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                    Ya pasó el día 5 del mes y todavía no hay un pago registrado. Este aviso va a seguir apareciendo hasta que se acredite el pago.
                </p>

                @if ($initPoint !== '')
                    <a
                        href="{{ $initPoint }}"
                        class="w-full flex items-center justify-center gap-2 rounded-xl bg-sky-500 hover:bg-sky-600 text-white text-sm font-semibold px-4 py-2.5 shadow-sm transition"
                    >
                        <x-heroicon-o-credit-card class="w-4 h-4" />
                        Pagar con Mercado Pago
                    </a>
                @else
                    <p class="text-sm text-amber-600 dark:text-amber-400">
                        No se pudo generar el link de pago. Probá de nuevo más tarde.
                    </p>
                @endif
            </div>
        </div>
    </div>
@endif
</div>
