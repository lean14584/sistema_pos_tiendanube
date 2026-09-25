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

    // Montos en millones ("$12.345.678,90") desbordaban la tarjeta: el
    // tamaño fijo de antes no entraba, y al no tener espacios el navegador
    // no lo cortaba solo. Se achica la letra según el largo Y además se
    // permite pasar a una segunda línea (break-words) — así nunca se sale
    // del recuadro sin importar cuántos dígitos tenga.
    $valueSize = match (true) {
        strlen((string) $value) > 14 => 'text-lg',
        strlen((string) $value) > 10 => 'text-xl',
        strlen((string) $value) > 7 => 'text-2xl',
        default => 'text-3xl',
    };
@endphp

<div class="relative overflow-hidden rounded-xl bg-gradient-to-br {{ $c }} text-white p-5 shadow-lg hover:-translate-y-0.5 transition-all">
    <p class="{{ $valueSize }} font-bold tracking-tight leading-tight break-words">{{ $value }}</p>
    <p class="text-sm font-medium text-white/90 mt-2">{{ $label }}</p>
    <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-24 h-24 absolute -right-4 -bottom-4 text-white/20 pointer-events-none" />
</div>
