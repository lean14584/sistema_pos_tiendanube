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
            'description' => $d['description'] ?? "{$debitLabel} {$d['label']}",
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

    $inputClass = 'w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-sky-400 transition';
    $sectionTitle = 'text-xs font-bold text-sky-700 dark:text-sky-400 uppercase tracking-wider';
@endphp

<div class="space-y-6">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="rounded-2xl border border-sky-200 dark:border-gray-800 bg-sky-100/70 dark:bg-gray-900 shadow-sm p-4">
            <p class="text-xs text-gray-400 dark:text-gray-500 uppercase mb-1">Total {{ strtolower($debitLabel) }}s</p>
            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">${{ money($totalDebit) }}</p>
        </div>
        <div class="rounded-2xl border border-sky-200 dark:border-gray-800 bg-sky-100/70 dark:bg-gray-900 shadow-sm p-4">
            <p class="text-xs text-gray-400 dark:text-gray-500 uppercase mb-1">Total {{ strtolower($paymentLabel) }}s</p>
            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">${{ money($totalCredit) }}</p>
        </div>
        <div class="rounded-2xl border border-sky-200 dark:border-gray-800 bg-sky-100/70 dark:bg-gray-900 shadow-sm p-4">
            <p class="text-xs text-gray-400 dark:text-gray-500 uppercase mb-1">{{ $balance > 0 ? $balanceOwedLabel : 'Saldo' }}</p>
            <p class="text-lg font-semibold {{ $balance > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                ${{ money(abs($balance)) }}
                {{ $balance <= 0 ? '(sin saldo pendiente)' : '' }}
            </p>
        </div>
    </div>

    <form wire:submit="addPayment" class="rounded-2xl border border-sky-200 dark:border-gray-800 bg-sky-100/70 dark:bg-gray-900 shadow-sm p-5">
        <h3 class="{{ $sectionTitle }} mb-3">Registrar {{ strtolower($paymentLabel) }}</h3>
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Fecha</label>
                <input type="date" wire:model="date" class="{{ $inputClass }}">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Monto</label>
                <input type="number" min="0" step="0.01" wire:model="amount" placeholder="0.00" class="{{ $inputClass }}">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Método</label>
                <select wire:model="method" class="{{ $inputClass }}">
                    @foreach ($paymentMethods as $m)
                        <option value="{{ $m->value }}">{{ $m->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Notas</label>
                <input type="text" wire:model="notes" class="{{ $inputClass }}">
            </div>
        </div>
        @error('amount') <p class="text-sm text-red-600 dark:text-red-400 mt-2">{{ $message }}</p> @enderror
        <button type="submit" class="mt-4 rounded-lg bg-sky-600 hover:bg-sky-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-all">
            Agregar {{ strtolower($paymentLabel) }}
        </button>
    </form>

    <div class="border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
        @if ($rows->isEmpty())
            <div class="p-10 text-center text-sm text-gray-400 dark:text-gray-500">Sin movimientos todavía.</div>
        @else
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50">
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
                                    <a href="{{ $row['href'] }}" wire:navigate class="text-sky-700 hover:text-sky-800 dark:text-sky-400 dark:hover:text-sky-300">{{ $row['description'] }}</a>
                                @else
                                    {{ $row['description'] }}
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right text-gray-700 dark:text-gray-300">{{ $row['debit'] ? '$'.money($row['debit']) : '—' }}</td>
                            <td class="px-5 py-3 text-right text-gray-700 dark:text-gray-300">{{ $row['credit'] ? '$'.money($row['credit']) : '—' }}</td>
                            <td class="px-5 py-3 text-right font-medium text-gray-900 dark:text-gray-100">${{ money($row['balance']) }}</td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                @if ($row['paymentId'])
                                    @if ($receiptRoute)
                                        <a
                                            href="{{ route($receiptRoute, $row['paymentId']) }}"
                                            target="_blank"
                                            title="Descargar recibo"
                                            class="inline-flex p-1.5 rounded-md text-gray-500 hover:bg-sky-50 hover:text-sky-600 dark:text-gray-400 dark:hover:bg-sky-500/10 dark:hover:text-sky-400 hover:scale-110 transition-all align-middle"
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
            </div>
        @endif
    </div>
</div>
