@props(['label', 'value', 'icon', 'color' => 'indigo'])

@php
    // Clases literales (no concatenadas) para que Tailwind las detecte al compilar.
    $colors = [
        'emerald' => 'from-emerald-500 to-emerald-600 shadow-emerald-500/30',
        'amber' => 'from-amber-500 to-amber-600 shadow-amber-500/30',
        'red' => 'from-red-500 to-red-600 shadow-red-500/30',
        'sky' => 'from-sky-500 to-sky-600 shadow-sky-500/30',
        'indigo' => 'from-indigo-500 to-indigo-600 shadow-indigo-500/30',
    ];
    $c = $colors[$color] ?? $colors['indigo'];
@endphp

<div class="relative overflow-hidden rounded-xl bg-gradient-to-br {{ $c }} text-white p-5 shadow-lg hover:-translate-y-0.5 transition-all">
    <p class="text-3xl font-bold tracking-tight leading-none">{{ $value }}</p>
    <p class="text-sm font-medium text-white/90 mt-2">{{ $label }}</p>
    <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-24 h-24 absolute -right-4 -bottom-4 text-white/20 pointer-events-none" />
</div>
