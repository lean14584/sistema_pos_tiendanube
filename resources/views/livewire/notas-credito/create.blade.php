@php
    $inputClass = 'w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors';
@endphp

<div class="p-8 max-w-3xl mx-auto">
    <a href="{{ route('invoices.show', $invoice) }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 mb-6">
        <x-heroicon-o-arrow-left class="w-4 h-4" />
        {{ $invoice->number }}
    </a>

    <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100 mb-2">Nueva Nota de Crédito</h1>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">
        Corresponde a la factura <strong>{{ $invoice->number }}</strong> ({{ $invoice->client->name }}). Se emitirá como
        {{ $invoice->tipo_comprobante === \App\Enums\TipoComprobante::FacturaA ? 'Nota de Crédito A' : 'Nota de Crédito B' }}.
    </p>

    <form wire:submit="save" class="space-y-6">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Ítems a acreditar</label>

            @error('items') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror

            @if (count($items) > 0)
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                    <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr class="text-left text-gray-500 dark:text-gray-400">
                                <th class="px-4 py-2 font-medium">Descripción</th>
                                <th class="px-4 py-2 font-medium w-24">Cant.</th>
                                <th class="px-4 py-2 font-medium w-32">Precio unit.</th>
                                <th class="px-4 py-2 font-medium w-28 text-right">Total</th>
                                <th class="px-2 py-2 w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $index => $item)
                                <tr wire:key="item-{{ $index }}" class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $item['description'] }}</td>
                                    <td class="px-4 py-2">
                                        <input type="number" min="0" step="1" wire:model="items.{{ $index }}.quantity" class="w-full rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                    </td>
                                    <td class="px-4 py-2">
                                        <input type="number" min="0" step="0.01" wire:model="items.{{ $index }}.unit_price" class="w-full rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                    </td>
                                    <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">
                                        ${{ number_format((float) $item['quantity'] * (float) $item['unit_price'], 2) }}
                                    </td>
                                    <td class="px-2 py-2 text-center">
                                        <button type="button" wire:click="removeItem({{ $index }})" class="text-gray-400 hover:text-red-600 dark:text-gray-500 dark:hover:text-red-400">
                                            <x-heroicon-o-trash class="w-4 h-4" />
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                </div>
            @else
                <p class="text-sm text-gray-400 dark:text-gray-500">No quedan ítems para acreditar.</p>
            @endif
        </div>

        <div>
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" wire:model="afecta_stock" class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500">
                Repone stock (marcá esto si la mercadería vuelve físicamente; desmarcalo si es solo una corrección de importe)
            </label>
        </div>

        <div class="flex justify-end">
            <div class="w-full max-w-xs space-y-2 text-sm">
                <div class="flex justify-between text-gray-600 dark:text-gray-400">
                    <span>Subtotal</span>
                    <span>${{ number_format($this->subtotal(), 2) }}</span>
                </div>
                <div class="flex justify-between text-gray-600 dark:text-gray-400">
                    <span>Impuesto ({{ $invoice->tax_rate }}%)</span>
                    <span>${{ number_format($this->taxAmount(), 2) }}</span>
                </div>
                <div class="flex justify-between font-semibold text-gray-900 dark:text-gray-100 text-base pt-2 border-t border-gray-200 dark:border-gray-800">
                    <span>Total a acreditar</span>
                    <span>${{ number_format($this->total(), 2) }}</span>
                </div>
            </div>
        </div>

        <div>
            <div class="flex items-center justify-between mb-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Reintegro al cliente</label>
                <button type="button" wire:click="addPayment" class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium">
                    <x-heroicon-o-plus class="w-3.5 h-3.5" />
                    Agregar reintegro
                </button>
            </div>

            @if (count($payments) === 0)
                <p class="text-sm text-gray-400 dark:text-gray-500">Sin reintegro registrado todavía.</p>
            @else
                <div class="space-y-2">
                    @foreach ($payments as $index => $payment)
                        <div wire:key="payment-{{ $index }}" class="flex items-center gap-2">
                            <select wire:model="payments.{{ $index }}.method" class="rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                @foreach ($paymentMethods as $method)
                                    <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                @endforeach
                            </select>
                            <input type="number" min="0" step="0.01" wire:model.live="payments.{{ $index }}.amount" class="w-32 rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm text-right focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                            <button type="button" wire:click="removePayment({{ $index }})" class="text-gray-400 hover:text-red-600 dark:text-gray-500 dark:hover:text-red-400">
                                <x-heroicon-o-trash class="w-4 h-4" />
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600 hover:shadow-lg hover:shadow-indigo-600/40 active:scale-[0.98] transition-all">
                Emitir Nota de Crédito
            </button>
            <a href="{{ route('invoices.show', $invoice) }}" wire:navigate class="rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-400 dark:hover:border-gray-600 hover:shadow-md active:scale-[0.98] transition-all">
                Cancelar
            </a>
        </div>
    </form>
</div>
