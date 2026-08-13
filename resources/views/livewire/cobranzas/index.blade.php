<div class="p-8 max-w-5xl mx-auto">
    <x-page-header title="Cobranzas" subtitle="Clientes con saldo pendiente. Registrá el cobro o mandá un recordatorio por WhatsApp." icon="banknotes">
        <x-slot:actions>
            <div class="text-right">
                <p class="text-[11px] uppercase tracking-wide text-white/70">Total a cobrar</p>
                <p class="text-2xl font-bold text-white">${{ number_format($totalACobrar, 2) }}</p>
            </div>
        </x-slot:actions>
    </x-page-header>

    @if (session('cobranza_ok'))
        <div class="mb-4 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 px-4 py-2.5 text-sm text-emerald-700 dark:text-emerald-300">
            {{ session('cobranza_ok') }}
        </div>
    @endif

    <div class="relative mb-4 max-w-xs">
        <x-heroicon-o-magnifying-glass class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="Buscar cliente..."
            class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
        >
    </div>

    <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800/50">
                <tr class="text-left text-gray-500 dark:text-gray-400">
                    <th class="px-4 py-2.5 font-medium">Cliente</th>
                    <th class="px-4 py-2.5 font-medium w-32 text-right">Saldo</th>
                    <th class="px-4 py-2.5 font-medium w-72 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($deudores as $row)
                    @php $client = $row['client']; @endphp
                    <tr class="border-t border-gray-100 dark:border-gray-800">
                        <td class="px-4 py-3">
                            <a href="{{ route('clients.account', $client) }}" wire:navigate class="font-medium text-gray-900 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">{{ $client->name }}</a>
                            @if ($client->phone)
                                <span class="block text-xs text-gray-400 dark:text-gray-500">{{ $client->phone }}</span>
                            @else
                                <span class="block text-xs text-amber-500 dark:text-amber-400">Sin teléfono cargado</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-gray-100">${{ number_format($row['saldo'], 2) }}</td>
                        <td class="px-4 py-3">
                            @if ($payingClientId === $client->id)
                                <div class="flex items-center justify-end gap-1.5">
                                    <input type="number" step="0.01" min="0.01" wire:model="payAmount" class="w-24 rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-2 py-1.5 text-sm text-right focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <select wire:model="payMethod" class="rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        @foreach ($paymentMethods as $pm)
                                            <option value="{{ $pm->value }}">{{ $pm->label() }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" wire:click="savePayment" class="rounded-md bg-emerald-600 hover:bg-emerald-700 px-2.5 py-1.5 text-white text-xs font-medium">Guardar</button>
                                    <button type="button" wire:click="cancelPayment" class="rounded-md border border-gray-300 dark:border-gray-700 px-2 py-1.5 text-gray-600 dark:text-gray-300 text-xs">✕</button>
                                </div>
                                @error('payAmount') <p class="text-xs text-red-600 dark:text-red-400 mt-1 text-right">{{ $message }}</p> @enderror
                            @else
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button" wire:click="startPayment({{ $client->id }}, {{ $row['saldo'] }})" class="inline-flex items-center gap-1 rounded-md bg-indigo-600 hover:bg-indigo-700 px-2.5 py-1.5 text-white text-xs font-medium">
                                        <x-heroicon-o-banknotes class="w-3.5 h-3.5" /> Cobrar
                                    </button>
                                    @if ($row['whatsapp'])
                                        <a href="{{ $row['whatsapp'] }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-md bg-green-600 hover:bg-green-700 px-2.5 py-1.5 text-white text-xs font-medium">
                                            <x-heroicon-o-chat-bubble-left-right class="w-3.5 h-3.5" /> WhatsApp
                                        </a>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-md bg-gray-100 dark:bg-gray-800 px-2.5 py-1.5 text-gray-400 dark:text-gray-500 text-xs cursor-not-allowed" title="Cargá el teléfono del cliente">
                                            <x-heroicon-o-chat-bubble-left-right class="w-3.5 h-3.5" /> WhatsApp
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-10 text-center text-gray-400 dark:text-gray-500">
                            {{ trim($search) !== '' ? 'Ningún cliente coincide con la búsqueda.' : '¡Sin deudores! Nadie tiene saldo pendiente.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
