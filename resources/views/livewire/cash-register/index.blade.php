<div class="p-8 max-w-5xl mx-auto">
    <x-page-header title="Caja" subtitle="Apertura, movimientos y cierre de caja de {{ $sucursalActiva?->name ?? 'tu sucursal' }}" icon="banknotes" />

    @if (! $openSession)
        <form wire:submit="openSession" class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-6 max-w-md">
            <div class="flex items-center gap-2 mb-4">
                <x-heroicon-o-lock-open class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                <h2 class="font-medium text-gray-900 dark:text-gray-100">Abrir caja</h2>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Monto de apertura *</label>
                    <input type="number" min="0" step="0.01" wire:model="openingAmount" placeholder="0.00" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    @error('openingAmount') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notas</label>
                    <input type="text" wire:model="openingNotes" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>
                <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600 hover:shadow-lg hover:shadow-indigo-600/40 active:scale-[0.98] transition-all disabled:opacity-50">
                    Abrir caja
                </button>
            </div>
        </form>
    @else
        <div class="space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-4">
                    <p class="text-xs text-gray-400 dark:text-gray-500 uppercase mb-1">Apertura</p>
                    <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">${{ money($openSession->opening_amount) }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ $openSession->opened_at->format('d/m/Y H:i') }} · {{ $openSession->user->name }}</p>
                </div>
                <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-4">
                    <p class="text-xs text-gray-400 dark:text-gray-500 uppercase mb-1">Ingresos</p>
                    <p class="text-lg font-semibold text-emerald-600 dark:text-emerald-400">${{ money($summary['ingresos']) }}</p>
                </div>
                <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-4">
                    <p class="text-xs text-gray-400 dark:text-gray-500 uppercase mb-1">Egresos</p>
                    <p class="text-lg font-semibold text-red-600 dark:text-red-400">${{ money($summary['egresos']) }}</p>
                </div>
                <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-4">
                    <p class="text-xs text-gray-400 dark:text-gray-500 uppercase mb-1">Saldo esperado</p>
                    <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">${{ money($summary['expectedClosing']) }}</p>
                </div>
            </div>

            <form wire:submit="addMovement" class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-4">
                <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Agregar movimiento</h3>
                <div class="grid grid-cols-1 sm:grid-cols-5 gap-3">
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Tipo</label>
                        <select wire:model="movType" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                            <option value="ingreso">Ingreso</option>
                            <option value="egreso">Egreso</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Concepto</label>
                        <input type="text" wire:model="movConcept" placeholder="Ej: Gastos de librería" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Monto</label>
                        <input type="number" min="0" step="0.01" wire:model="movAmount" placeholder="0.00" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Fecha</label>
                        <input type="date" wire:model="movDate" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>
                </div>
                <button type="submit" wire:loading.attr="disabled" class="mt-3 rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600 hover:shadow-lg hover:shadow-indigo-600/40 active:scale-[0.98] transition-all disabled:opacity-50">
                    Agregar movimiento
                </button>
            </form>

            <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40">
                @if ($sessionMovements->isEmpty())
                    <div class="p-10 text-center text-sm text-gray-400 dark:text-gray-500">Sin movimientos todavía en esta caja.</div>
                @else
                    <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800 bg-gray-100/80 dark:bg-gray-800/40">
                                <th class="px-5 py-3 font-medium">Fecha</th>
                                <th class="px-5 py-3 font-medium">Concepto</th>
                                <th class="px-5 py-3 font-medium">Origen</th>
                                <th class="px-5 py-3 font-medium text-right">Ingreso</th>
                                <th class="px-5 py-3 font-medium text-right">Egreso</th>
                                <th class="px-5 py-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sessionMovements as $m)
                                <tr class="border-b border-gray-50 dark:border-gray-800/60 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                    <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ $m->date->format('d/m/Y') }}</td>
                                    <td class="px-5 py-3 text-gray-700 dark:text-gray-300">{{ $m->concept }}</td>
                                    <td class="px-5 py-3 text-gray-500 dark:text-gray-400 capitalize">{{ $m->source->value }}</td>
                                    <td class="px-5 py-3 text-right text-emerald-600 dark:text-emerald-400">{{ $m->type->value === 'ingreso' ? '$'.money($m->amount) : '—' }}</td>
                                    <td class="px-5 py-3 text-right text-red-600 dark:text-red-400">{{ $m->type->value === 'egreso' ? '$'.money($m->amount) : '—' }}</td>
                                    <td class="px-5 py-3 text-right">
                                        @if ($m->source->value === 'manual')
                                            <button wire:click="deleteMovement({{ $m->id }})" class="p-1.5 rounded-md text-gray-500 hover:bg-red-50 hover:text-red-600 dark:text-gray-400 dark:hover:bg-red-500/10 dark:hover:text-red-400 hover:scale-110 transition-all">
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

            <form x-on:submit.prevent="confirmThen('¿Confirmás el cierre de caja?', () => $wire.closeSession())" class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-4">
                <div class="flex items-center gap-2 mb-3">
                    <x-heroicon-o-lock-closed class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                    <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">Cerrar caja</h3>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Monto de cierre (real) *</label>
                        <input type="number" min="0" step="0.01" wire:model="closingAmount" placeholder="{{ money($summary['expectedClosing']) }}" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        @error('closingAmount') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Notas</label>
                        <input type="text" wire:model="closingNotes" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>
                </div>
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="mt-3 rounded-lg border border-red-300 dark:border-red-500/30 px-4 py-2 text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors disabled:opacity-50"
                >
                    Cerrar caja
                </button>
            </form>
        </div>
    @endif

    <div class="mt-10">
        <h2 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Historial de cajas</h2>
        <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40">
            @if ($closedSessions->isEmpty())
                <div class="p-10 text-center text-gray-400 dark:text-gray-500">
                    <x-heroicon-o-banknotes class="w-10 h-10 mx-auto mb-3 text-gray-300 dark:text-gray-700" />
                    <p class="text-sm">Todavía no hay cajas cerradas.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800 bg-gray-100/80 dark:bg-gray-800/40">
                            <th class="px-5 py-3 font-medium">Usuario</th>
                            <th class="px-5 py-3 font-medium">Apertura</th>
                            <th class="px-5 py-3 font-medium">Cierre</th>
                            <th class="px-5 py-3 font-medium text-right">Ingresos</th>
                            <th class="px-5 py-3 font-medium text-right">Egresos</th>
                            <th class="px-5 py-3 font-medium text-right">Diferencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($closedSessions as $s)
                            @php
                                $ingresos = $s->movements->where('type', \App\Enums\CashMovementType::Ingreso)->sum('amount');
                                $egresos = $s->movements->where('type', \App\Enums\CashMovementType::Egreso)->sum('amount');
                                $expected = (float) $s->opening_amount + $ingresos - $egresos;
                                $difference = (float) $s->closing_amount - $expected;
                            @endphp
                            <tr class="border-b border-gray-50 dark:border-gray-800/60 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <td class="px-5 py-3 text-gray-700 dark:text-gray-300">{{ $s->user->name }}</td>
                                <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ $s->opened_at->format('d/m/Y H:i') }} · ${{ money($s->opening_amount) }}</td>
                                <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ $s->closed_at?->format('d/m/Y H:i') }} · ${{ money($s->closing_amount) }}</td>
                                <td class="px-5 py-3 text-right text-emerald-600 dark:text-emerald-400">${{ money($ingresos) }}</td>
                                <td class="px-5 py-3 text-right text-red-600 dark:text-red-400">${{ money($egresos) }}</td>
                                <td class="px-5 py-3 text-right font-medium {{ abs($difference) > 0.01 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-gray-100' }}">
                                    ${{ money($difference) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </div>
    </div>
</div>
