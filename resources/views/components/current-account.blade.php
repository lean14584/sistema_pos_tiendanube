@props([
    'debitLabel',
    'paymentLabel',
    'balanceOwedLabel',
    'debits',
    'payments',
    'paymentMethods',
    'method' => 'efectivo',
    'amount' => '',
    'date' => '',
    'notes' => '',
    'receiptRoute' => null,
    'whatsappPhone' => null,
    'clientName' => '',
])

@php
    // `amount` puede venir con signo negativo (Nota de Crédito/Devolución):
    // esas líneas van a la columna "Haber", no a "Debe".
    $movements = collect($debits)->map(function ($d) use ($debitLabel) {
        $amount = (float) $d['amount'];

        return [
            'date' => $d['date'],
            'description' => "{$debitLabel} {$d['label']}",
            'debit' => max($amount, 0.0),
            'credit' => max(-$amount, 0.0),
            'href' => $d['href'] ?? null,
            'paymentId' => null,
        ];
    })->merge(
        collect($payments)->map(fn ($p) => [
            'date' => $p->date->toDateString(),
            'description' => "{$paymentLabel} · {$p->method->label()}".($p->notes ? " · {$p->notes}" : ''),
            'debit' => 0,
            'credit' => (float) $p->amount,
            'href' => null,
            'paymentId' => $p->id,
        ])
    )->sortBy('date')->values();

    $running = 0;
    $rows = $movements->map(function ($m) use (&$running) {
        $running += $m['debit'] - $m['credit'];
        $m['balance'] = $running;

        return $m;
    });

    $totalDebit = $movements->sum('debit');
    $totalCredit = $movements->sum('credit');
    $balance = $totalDebit - $totalCredit;
@endphp

<div class="space-y-6">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-4">
            <p class="text-xs text-gray-400 dark:text-gray-500 uppercase mb-1">Total {{ strtolower($debitLabel) }}s</p>
            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">${{ number_format($totalDebit, 2) }}</p>
        </div>
        <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-4">
            <p class="text-xs text-gray-400 dark:text-gray-500 uppercase mb-1">Total {{ strtolower($paymentLabel) }}s</p>
            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">${{ number_format($totalCredit, 2) }}</p>
        </div>
        <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-4">
            <p class="text-xs text-gray-400 dark:text-gray-500 uppercase mb-1">{{ $balance > 0 ? $balanceOwedLabel : 'Saldo' }}</p>
            <p class="text-lg font-semibold {{ $balance > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                ${{ number_format(abs($balance), 2) }}
                {{ $balance <= 0 ? '(sin saldo pendiente)' : '' }}
            </p>
        </div>
    </div>

    <form wire:submit="addPayment" class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-4">
        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Registrar {{ strtolower($paymentLabel) }}</h3>
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Fecha</label>
                <input type="date" wire:model="date" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
            <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Monto</label>
                <input type="number" min="0" step="0.01" wire:model="amount" placeholder="0.00" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
            <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Método</label>
                <select wire:model="method" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    @foreach ($paymentMethods as $m)
                        <option value="{{ $m->value }}">{{ $m->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Notas</label>
                <input type="text" wire:model="notes" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
        </div>
        @error('amount') <p class="text-sm text-red-600 dark:text-red-400 mt-2">{{ $message }}</p> @enderror
        <button type="submit" class="mt-3 rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600 hover:shadow-lg hover:shadow-indigo-600/40 active:scale-[0.98] transition-all">
            Agregar {{ strtolower($paymentLabel) }}
        </button>
    </form>

    <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40">
        @if ($rows->isEmpty())
            <div class="p-10 text-center text-sm text-gray-400 dark:text-gray-500">Sin movimientos todavía.</div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800 bg-gray-100/80 dark:bg-gray-800/40">
                        <th class="px-5 py-3 font-medium">Fecha</th>
                        <th class="px-5 py-3 font-medium">Detalle</th>
                        <th class="px-5 py-3 font-medium text-right">Debe</th>
                        <th class="px-5 py-3 font-medium text-right">Haber</th>
                        <th class="px-5 py-3 font-medium text-right">Saldo</th>
                        <th class="px-5 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b border-gray-50 dark:border-gray-800/60 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                            <td class="px-5 py-3 text-gray-700 dark:text-gray-300">
                                @if ($row['href'])
                                    <a href="{{ $row['href'] }}" wire:navigate class="text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300">{{ $row['description'] }}</a>
                                @else
                                    {{ $row['description'] }}
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right text-gray-700 dark:text-gray-300">{{ $row['debit'] ? '$'.number_format($row['debit'], 2) : '—' }}</td>
                            <td class="px-5 py-3 text-right text-gray-700 dark:text-gray-300">{{ $row['credit'] ? '$'.number_format($row['credit'], 2) : '—' }}</td>
                            <td class="px-5 py-3 text-right font-medium text-gray-900 dark:text-gray-100">${{ number_format($row['balance'], 2) }}</td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                @if ($row['paymentId'])
                                    @if ($receiptRoute)
                                        <a
                                            href="{{ route($receiptRoute, $row['paymentId']) }}"
                                            target="_blank"
                                            title="Descargar recibo"
                                            class="inline-flex p-1.5 rounded-md text-gray-500 hover:bg-indigo-50 hover:text-indigo-600 dark:text-gray-400 dark:hover:bg-indigo-500/10 dark:hover:text-indigo-400 hover:scale-110 transition-all align-middle"
                                        >
                                            <x-heroicon-o-document-arrow-down class="w-4 h-4" />
                                        </a>
                                    @endif
                                    @if ($whatsappPhone)
                                        @php
                                            $reciboMsg = 'Hola '.$clientName.', te paso el recibo por tu pago de $'
                                                .number_format($row['credit'], 2, ',', '.').' del '
                                                .\Illuminate\Support\Carbon::parse($row['date'])->format('d/m/Y')
                                                .'. Saldo restante: $'.number_format(max(0, $row['balance']), 2, ',', '.')
                                                .'. Gracias!';
                                            $reciboWa = \App\Support\Whatsapp::link($whatsappPhone, $reciboMsg);
                                        @endphp
                                        @if ($reciboWa)
                                            <a
                                                href="{{ $reciboWa }}"
                                                target="_blank"
                                                title="Enviar recibo por WhatsApp"
                                                class="inline-flex p-1.5 rounded-md text-gray-500 hover:bg-green-50 hover:text-green-600 dark:text-gray-400 dark:hover:bg-green-500/10 dark:hover:text-green-400 hover:scale-110 transition-all align-middle"
                                            >
                                                <x-heroicon-o-chat-bubble-left-right class="w-4 h-4" />
                                            </a>
                                        @endif
                                    @endif
                                    <button
                                        x-on:click="confirmThen('¿Eliminar este movimiento?', () => $wire.deletePayment({{ $row['paymentId'] }}))"
                                        class="inline-flex p-1.5 rounded-md text-gray-500 hover:bg-red-50 hover:text-red-600 dark:text-gray-400 dark:hover:bg-red-500/10 dark:hover:text-red-400 hover:scale-110 transition-all align-middle"
                                    >
                                        <x-heroicon-o-trash class="w-4 h-4" />
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
