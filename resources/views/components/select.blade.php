@props(['label' => null, 'wrapperClass' => ''])

<div class="{{ $wrapperClass }}">
    @if ($label)
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $label }}</label>
    @endif
    <select {{ $attributes->merge(['class' => 'w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 text-gray-800 dark:text-gray-100 px-3 py-2.5 text-sm shadow-sm hover:border-indigo-300 dark:hover:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 disabled:opacity-60 disabled:cursor-not-allowed transition cursor-pointer']) }}>
        {{ $slot }}
    </select>
</div>
