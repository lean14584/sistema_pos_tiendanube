<div class="p-8 max-w-3xl mx-auto">
    <a href="{{ route('stock-transfers.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 mb-6">
        <x-heroicon-o-arrow-left class="w-4 h-4" /> Envíos de mercadería
    </a>

    <x-page-header title="Envío #{{ str_pad($transfer->id, 8, '0', STR_PAD_LEFT) }}" icon="arrows-right-left">
        <x-slot:actions>
            <a href="{{ route('stock-transfers.pdf', $transfer) }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-800">
                <x-heroicon-o-printer class="w-4 h-4" /> Imprimir / PDF
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md p-5 mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
            <div>
                <p class="text-xs text-gray-400 dark:text-gray-500 uppercase mb-1">Origen</p>
                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $transfer->fromSucursal->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 dark:text-gray-500 uppercase mb-1">Destino</p>
                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $transfer->toSucursal->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 dark:text-gray-500 uppercase mb-1">Estado</p>
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $transfer->status->colorClasses() }}">
                    {{ $transfer->status->label() }}
                </span>
            </div>
        </div>

        <p class="text-xs text-gray-400 dark:text-gray-500 mb-4">
            Enviado por {{ $transfer->user->name ?? 'Sistema' }} el {{ $transfer->created_at->format('d/m/Y H:i') }}
            @if ($transfer->status->value === 'recibido')
                · Recibido por {{ $transfer->receivedBy->name ?? 'Sistema' }} el {{ $transfer->received_at?->format('d/m/Y H:i') }}
            @endif
        </p>

        @if ($transfer->notes)
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4"><strong>Notas:</strong> {{ $transfer->notes }}</p>
        @endif

        <form wire:submit="confirmarRecepcion">
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-gray-500 dark:text-gray-400">
                            <th class="px-3 py-2 font-medium">Producto</th>
                            <th class="px-3 py-2 font-medium w-32">Enviado</th>
                            <th class="px-3 py-2 font-medium w-32">Recibido</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transfer->items as $index => $item)
                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $item->product->name ?? 'Producto eliminado' }}</td>
                                <td class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ $item->quantity }}</td>
                                <td class="px-3 py-2">
                                    @if ($puedeConfirmar)
                                        <input type="number" min="0" max="{{ $item->quantity }}" wire:model="received.{{ $index }}" class="w-24 rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                        @error("received.{$index}") <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                                    @else
                                        {{ $item->quantity_received ?? '—' }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($puedeConfirmar)
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-3 mb-3">
                    Si recibiste menos de lo que se envió (rotura o pérdida en el traslado), corregí la cantidad — esa diferencia no se acredita en ningún lado.
                </p>
                <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md hover:from-indigo-700 hover:to-indigo-600 disabled:opacity-50">
                    Confirmar recepción
                </button>
            @endif
        </form>
    </div>
</div>
