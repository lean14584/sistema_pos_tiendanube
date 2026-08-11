<div class="p-8 max-w-4xl mx-auto">
    <a href="{{ route('purchases.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 mb-6">
        <x-heroicon-o-arrow-left class="w-4 h-4" />
        Compras
    </a>

    <div class="flex items-start justify-between mb-8">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $purchase->number }}</h1>
                <x-status-badge :status="$purchase->effective_status" />
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Comprada el {{ $purchase->issue_date->format('d/m/Y') }} · Vence el {{ $purchase->due_date->format('d/m/Y') }}
            </p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('purchases.edit', $purchase) }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-400 dark:hover:border-gray-600 hover:shadow-md active:scale-[0.98] transition-all">
                <x-heroicon-o-pencil class="w-4 h-4" />
                Editar
            </a>
            <button
                wire:click="delete"
                wire:confirm="¿Eliminar la compra {{ $purchase->number }}? Esto revertirá el stock sumado por esta compra."
                class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-700 px-3 py-2 text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors"
            >
                <x-heroicon-o-trash class="w-4 h-4" />
            </button>
        </div>
    </div>

    <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-6 mb-6">
        <div class="flex items-center gap-3 mb-6">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Estado:</span>
            <div class="flex gap-1.5">
                @foreach ($statuses as $s)
                    <button
                        wire:click="setStatus('{{ $s->value }}')"
                        class="px-3 py-1 rounded-full text-xs font-medium transition-colors {{ $purchase->status === $s ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700' }}"
                    >
                        {{ $s->label() }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6 pb-6 border-b border-gray-100 dark:border-gray-800">
            <div>
                <p class="text-xs uppercase text-gray-400 dark:text-gray-500 mb-1">Comprado a</p>
                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $purchase->provider->name }}</p>
                @if ($purchase->provider->email)<p class="text-sm text-gray-500 dark:text-gray-400">{{ $purchase->provider->email }}</p>@endif
                @if ($purchase->provider->address)<p class="text-sm text-gray-500 dark:text-gray-400">{{ $purchase->provider->address }}</p>@endif
                @if ($purchase->provider->tax_id)<p class="text-sm text-gray-500 dark:text-gray-400">ID fiscal: {{ $purchase->provider->tax_id }}</p>@endif
            </div>
        </div>

        <div class="overflow-x-auto">
        <table class="w-full text-sm mb-6">
            <thead>
                <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800 bg-gray-100/80 dark:bg-gray-800/40">
                    <th class="py-2 font-medium">Descripción</th>
                    <th class="py-2 font-medium text-right">Cant.</th>
                    <th class="py-2 font-medium text-right">Precio unit.</th>
                    <th class="py-2 font-medium text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($purchase->items as $item)
                    <tr class="border-b border-gray-50 dark:border-gray-800/60 last:border-0">
                        <td class="py-2.5 text-gray-800 dark:text-gray-200">{{ $item->description }}</td>
                        <td class="py-2.5 text-right text-gray-600 dark:text-gray-400">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                        <td class="py-2.5 text-right text-gray-600 dark:text-gray-400">${{ number_format($item->unit_price, 2) }}</td>
                        <td class="py-2.5 text-right font-medium text-gray-900 dark:text-gray-100">${{ number_format($item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>

        <div class="flex justify-end">
            <div class="w-full max-w-xs space-y-2 text-sm">
                <div class="flex justify-between text-gray-600 dark:text-gray-400">
                    <span>Subtotal</span>
                    <span>${{ number_format($purchase->subtotal, 2) }}</span>
                </div>
                <div class="flex justify-between text-gray-600 dark:text-gray-400">
                    <span>Impuesto ({{ $purchase->tax_rate }}%)</span>
                    <span>${{ number_format($purchase->tax_amount, 2) }}</span>
                </div>
                <div class="flex justify-between font-semibold text-gray-900 dark:text-gray-100 text-base pt-2 border-t border-gray-200 dark:border-gray-800">
                    <span>Total</span>
                    <span>${{ number_format($purchase->total, 2) }}</span>
                </div>
            </div>
        </div>

        @if ($purchase->payments->isNotEmpty())
            <div class="mt-6 pt-6 border-t border-gray-100 dark:border-gray-800">
                <p class="text-xs uppercase text-gray-400 dark:text-gray-500 mb-2">Métodos de pago</p>
                <div class="space-y-1">
                    @foreach ($purchase->payments as $payment)
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">{{ $payment->method->label() }}</span>
                            <span class="text-gray-900 dark:text-gray-100 font-medium">${{ number_format($payment->amount, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($purchase->notes)
            <div class="mt-6 pt-6 border-t border-gray-100 dark:border-gray-800">
                <p class="text-xs uppercase text-gray-400 dark:text-gray-500 mb-1">Notas</p>
                <p class="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-wrap">{{ $purchase->notes }}</p>
            </div>
        @endif
    </div>
</div>
