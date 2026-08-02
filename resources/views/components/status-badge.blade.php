@props(['status'])

<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset shadow-sm {{ $status->colorClasses() }}">
    <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
    {{ $status->label() }}
</span>
