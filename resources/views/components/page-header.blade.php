@props([
    'title',
    'subtitle' => null,
    'icon' => null,
])

{{-- Barra de encabezado con degradé azul/violeta, igual que el header del POS. --}}
<div {{ $attributes->merge(['class' => 'mb-6 rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 text-white px-5 py-4 shadow-md shadow-indigo-600/20']) }}>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
            @if ($icon)
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-white/15 shrink-0">
                    <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-5 h-5" />
                </span>
            @endif
            <div class="min-w-0">
                <h1 class="text-xl font-bold leading-tight">{{ $title }}</h1>
                @if ($subtitle)
                    <p class="text-xs text-white/80 mt-0.5">{{ $subtitle }}</p>
                @endif
            </div>
        </div>
        @isset($actions)
            <div class="flex items-center gap-2 shrink-0">
                {{ $actions }}
            </div>
        @endisset
    </div>
</div>
