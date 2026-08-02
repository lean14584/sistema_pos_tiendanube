@props(['label', 'value', 'icon', 'accent' => 'text-indigo-600 bg-indigo-50 dark:text-indigo-400 dark:bg-indigo-500/10'])

<div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 hover:shadow-md hover:-translate-y-0.5 transition-all p-5 flex items-center gap-4">
    <div class="w-11 h-11 rounded-xl flex items-center justify-center ring-1 ring-inset ring-black/5 dark:ring-white/10 {{ $accent }}">
        <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-5 h-5" />
    </div>
    <div>
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</p>
        <p class="text-xl font-semibold text-gray-900 dark:text-gray-100 tracking-tight">{{ $value }}</p>
    </div>
</div>
