@php
    $eventoLabel = ['created' => 'Alta', 'updated' => 'Modificación', 'deleted' => 'Baja'];
    $eventoColor = [
        'created' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
        'updated' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
        'deleted' => 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',
    ];

    $rutaRegistro = function ($log) {
        if (! $log->auditable) {
            return null;
        }

        return match ($log->auditable_type) {
            \App\Models\Invoice::class => route('invoices.show', $log->auditable_id),
            \App\Models\Product::class => route('products.edit', $log->auditable_id),
            \App\Models\User::class => route('users.edit', $log->auditable_id),
            \App\Models\CompanySettings::class => route('company-settings.edit'),
            default => null,
        };
    };
@endphp

<div class="p-8 max-w-6xl mx-auto">
    <x-page-header title="Auditoría" subtitle="Altas, bajas y modificaciones en facturas, productos, usuarios y datos de la empresa." icon="clipboard-document-check" />

    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 mb-6">
        <select wire:model.live="modelo" class="rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option value="">Todos los modelos</option>
            @foreach ($tiposAuditados as $clase => $etiqueta)
                <option value="{{ $clase }}">{{ $etiqueta }}</option>
            @endforeach
        </select>
        <select wire:model.live="userId" class="rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option value="">Todos los usuarios</option>
            @foreach ($usuarios as $usuario)
                <option value="{{ $usuario->id }}">{{ $usuario->name }}</option>
            @endforeach
        </select>
        <input type="date" wire:model.live="desde" class="rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="Desde">
        <input type="date" wire:model.live="hasta" class="rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="Hasta">
    </div>

    <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40">
        @if ($logs->isEmpty())
            <div class="p-12 text-center text-gray-400 dark:text-gray-500">
                <x-heroicon-o-clipboard-document-check class="w-10 h-10 mx-auto mb-3 text-gray-300 dark:text-gray-700" />
                <p class="text-sm">No hay actividad registrada con estos filtros.</p>
            </div>
        @else
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800 bg-gray-100/80 dark:bg-gray-800/40">
                        <th class="px-4 py-2 font-medium">Fecha</th>
                        <th class="px-4 py-2 font-medium">Usuario</th>
                        <th class="px-4 py-2 font-medium">Modelo</th>
                        <th class="px-4 py-2 font-medium">Evento</th>
                        <th class="px-4 py-2 font-medium">Cambios</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr class="border-b border-gray-50 dark:border-gray-800/60 last:border-0 align-top">
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $log->user->name ?? 'Sistema' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                @if ($rutaRegistro($log))
                                    <a href="{{ $rutaRegistro($log) }}" wire:navigate class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                        {{ \App\Models\AuditLog::etiquetaModelo($log->auditable_type) }} #{{ $log->auditable_id }}
                                    </a>
                                @else
                                    {{ \App\Models\AuditLog::etiquetaModelo($log->auditable_type) }} #{{ $log->auditable_id }}
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $eventoColor[$log->event] }}">
                                    {{ $eventoLabel[$log->event] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                <div class="space-y-0.5">
                                    @foreach ($log->changes as $campo => $valores)
                                        <div>
                                            <span class="text-gray-400 dark:text-gray-500">{{ $campo }}:</span>
                                            {{ $valores['old'] ?? '—' }} → {{ $valores['new'] ?? '—' }}
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>

            <div class="p-4 border-t border-gray-100 dark:border-gray-800">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
