<div class="p-8 max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Presupuestos</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Cotizaciones para tus clientes</p>
        </div>
        <a href="{{ route('quotes.create') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600 hover:shadow-lg hover:shadow-indigo-600/40 active:scale-[0.98] transition-all">
            <x-heroicon-o-plus class="w-4 h-4" />
            Nuevo presupuesto
        </a>
    </div>

    <div class="flex flex-col sm:flex-row gap-3 mb-5">
        <div class="relative flex-1 max-w-sm">
            <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" />
            <input
                type="text"
                wire:model.live.debounce.300ms="query"
                placeholder="Buscar por número o cliente..."
                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover:border-gray-400 dark:hover:border-gray-600 transition-colors"
            >
        </div>
        <div class="flex gap-1.5 flex-wrap">
            <button wire:click="$set('filter', 'all')" class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors {{ $filter === 'all' ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800' }}">
                Todos
            </button>
            @foreach ($statuses as $s)
                <button wire:click="$set('filter', '{{ $s->value }}')" class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors {{ $filter === $s->value ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800' }}">
                    {{ $s->label() }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40">
        @if ($quotes->isEmpty())
            <div class="p-12 text-center text-gray-400 dark:text-gray-500">
                <x-heroicon-o-clipboard-document-list class="w-10 h-10 mx-auto mb-3 text-gray-300 dark:text-gray-700" />
                <p class="text-sm">No se encontraron presupuestos.</p>
            </div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800 bg-gray-100/80 dark:bg-gray-800/40">
                        <th class="px-5 py-3 font-medium">N°</th>
                        <th class="px-5 py-3 font-medium">Cliente</th>
                        <th class="px-5 py-3 font-medium">Emisión</th>
                        <th class="px-5 py-3 font-medium">Válido hasta</th>
                        <th class="px-5 py-3 font-medium">Estado</th>
                        <th class="px-5 py-3 font-medium text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($quotes as $quote)
                        <tr wire:key="quote-{{ $quote->id }}" class="border-b border-gray-50 dark:border-gray-800/60 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="px-5 py-3">
                                <a href="{{ route('quotes.show', $quote) }}" wire:navigate class="text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium">
                                    {{ $quote->number }}
                                </a>
                            </td>
                            <td class="px-5 py-3 text-gray-700 dark:text-gray-300">{{ $quote->client->name ?? 'Cliente desconocido' }}</td>
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ $quote->issue_date->format('d/m/Y') }}</td>
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ $quote->valid_until->format('d/m/Y') }}</td>
                            <td class="px-5 py-3"><x-status-badge :status="$quote->status" /></td>
                            <td class="px-5 py-3 text-right font-medium text-gray-900 dark:text-gray-100">${{ number_format($quote->total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="p-4 border-t border-gray-100 dark:border-gray-800">
                {{ $quotes->links() }}
            </div>
        @endif
    </div>
</div>
