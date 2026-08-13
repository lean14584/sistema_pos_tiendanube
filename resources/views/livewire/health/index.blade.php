@php
    $estilos = [
        'ok' => ['dot' => 'bg-emerald-500', 'chip' => 'text-emerald-700 bg-emerald-50 dark:text-emerald-400 dark:bg-emerald-500/10', 'txt' => 'OK'],
        'warning' => ['dot' => 'bg-amber-500', 'chip' => 'text-amber-700 bg-amber-50 dark:text-amber-400 dark:bg-amber-500/10', 'txt' => 'Atención'],
        'error' => ['dot' => 'bg-red-500', 'chip' => 'text-red-700 bg-red-50 dark:text-red-400 dark:bg-red-500/10', 'txt' => 'Error'],
        'info' => ['dot' => 'bg-gray-400', 'chip' => 'text-gray-600 bg-gray-100 dark:text-gray-400 dark:bg-gray-500/10', 'txt' => 'Info'],
    ];
@endphp

<div class="p-8 max-w-3xl mx-auto">
    <x-page-header title="Estado del sistema" subtitle="Chequeos rápidos de lo que conviene tener al día." icon="heart" />

    @if ($avisos === 0)
        <div class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 dark:border-emerald-500/30 dark:bg-emerald-500/10 px-4 py-3 mb-6">
            <x-heroicon-o-check-circle class="w-6 h-6 text-emerald-600 dark:text-emerald-400" />
            <p class="text-sm font-medium text-emerald-800 dark:text-emerald-300">Todo en orden. No hay avisos.</p>
        </div>
    @else
        <div class="flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 dark:border-amber-500/30 dark:bg-amber-500/10 px-4 py-3 mb-6">
            <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-amber-600 dark:text-amber-400" />
            <p class="text-sm font-medium text-amber-800 dark:text-amber-300">Hay {{ $avisos }} aviso(s) para revisar.</p>
        </div>
    @endif

    <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 divide-y divide-gray-100 dark:divide-gray-800">
        @foreach ($chequeos as $c)
            @php $e = $estilos[$c['estado']]; @endphp
            <div class="flex items-center gap-3 px-5 py-4">
                <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $e['dot'] }}"></span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $c['label'] }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $c['detalle'] }}</p>
                </div>
                <span class="shrink-0 text-xs font-medium px-2 py-0.5 rounded-full {{ $e['chip'] }}">{{ $e['txt'] }}</span>
            </div>
        @endforeach
    </div>
</div>
