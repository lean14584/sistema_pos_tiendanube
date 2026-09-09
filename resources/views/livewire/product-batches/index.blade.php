<div class="p-8 max-w-6xl mx-auto">
    <a href="{{ route('products.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 mb-6">
        <x-heroicon-o-arrow-left class="w-4 h-4" /> Productos
    </a>
    <x-page-header title="Lotes y Vencimientos" subtitle="Lotes de productos perecederos cargados al recibir mercadería, con su fecha de vencimiento." icon="calendar-days" />

    @php $inputClass = 'w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent'; @endphp

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
        @if ($puedeVerTodasLasSucursales)
            <select wire:model.live="sucursal_id" class="{{ $inputClass }}">
                <option value="">Todas las sucursales</option>
                @foreach ($sucursales as $s)
                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                @endforeach
            </select>
        @endif
        <select wire:model.live="estado" class="{{ $inputClass }}">
            <option value="">Todos los estados</option>
            <option value="vencido">Vencidos</option>
            <option value="por_vencer">Por vencer (≤ {{ \App\Models\ProductBatch::DIAS_ALERTA }} días)</option>
            <option value="ok">OK</option>
        </select>
    </div>

    <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
        <div class="hidden sm:block overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800/50">
                <tr class="text-left text-gray-500 dark:text-gray-400">
                    <th class="px-4 py-2.5 font-medium">Producto</th>
                    @if ($puedeVerTodasLasSucursales)
                        <th class="px-4 py-2.5 font-medium">Sucursal</th>
                    @endif
                    <th class="px-4 py-2.5 font-medium">Lote</th>
                    <th class="px-4 py-2.5 font-medium">Cantidad restante</th>
                    <th class="px-4 py-2.5 font-medium">Vencimiento</th>
                    <th class="px-4 py-2.5 font-medium">Estado</th>
                    <th class="px-2 py-2.5 w-32"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($batches as $batch)
                    <tr class="border-t border-gray-100 dark:border-gray-800">
                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $batch->product->name ?? 'Producto eliminado' }}</td>
                        @if ($puedeVerTodasLasSucursales)
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $batch->sucursal->name ?? '—' }}</td>
                        @endif
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $batch->batch_number ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ rtrim(rtrim(number_format((float) $batch->quantity_remaining, 2, ',', '.'), '0'), ',') }}</td>
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $batch->expiration_date->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $batch->status->colorClasses() }}">
                                {{ $batch->status->label() }}
                                @if ($batch->status->value !== 'ok')
                                    ({{ $batch->dias_para_vencer >= 0 ? $batch->dias_para_vencer.' d' : 'hace '.abs($batch->dias_para_vencer).' d' }})
                                @endif
                            </span>
                        </td>
                        <td class="px-2 py-2 text-right">
                            <button type="button" wire:click="abrirBaja({{ $batch->id }})" class="text-xs text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 font-medium">
                                Dar de baja
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400 dark:text-gray-500">No hay lotes cargados con estos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>

        <div class="sm:hidden divide-y divide-gray-100 dark:divide-gray-800">
            @forelse ($batches as $batch)
                <div class="p-4">
                    <div class="flex items-start justify-between gap-3">
                        <p class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ $batch->product->name ?? 'Producto eliminado' }}</p>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset shrink-0 {{ $batch->status->colorClasses() }}">
                            {{ $batch->status->label() }}
                            @if ($batch->status->value !== 'ok')
                                ({{ $batch->dias_para_vencer >= 0 ? $batch->dias_para_vencer.' d' : 'hace '.abs($batch->dias_para_vencer).' d' }})
                            @endif
                        </span>
                    </div>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                        @if ($puedeVerTodasLasSucursales)
                            {{ $batch->sucursal->name ?? '—' }} ·
                        @endif
                        Lote {{ $batch->batch_number ?: '—' }} · Vence {{ $batch->expiration_date->format('d/m/Y') }}
                    </p>
                    <div class="flex items-center justify-between mt-1.5">
                        <p class="text-sm text-gray-700 dark:text-gray-300">Restante: {{ rtrim(rtrim(number_format((float) $batch->quantity_remaining, 2, ',', '.'), '0'), ',') }}</p>
                        <button type="button" wire:click="abrirBaja({{ $batch->id }})" class="text-xs text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 font-medium">
                            Dar de baja
                        </button>
                    </div>
                </div>
            @empty
                <div class="p-10 text-center text-gray-400 dark:text-gray-500">No hay lotes cargados con estos filtros.</div>
            @endforelse
        </div>

        <div class="p-4 border-t border-gray-100 dark:border-gray-800">
            {{ $batches->links() }}
        </div>
    </div>

    @if ($bajaBatchId !== null)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:click.self="cancelarBaja">
            <div class="bg-white dark:bg-gray-900 rounded-xl shadow-xl border border-gray-200 dark:border-gray-800 w-full max-w-sm p-5">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">Dar de baja lote</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Descuenta stock del producto (motivo: Vencimiento) y reduce la cantidad restante del lote.</p>

                <form wire:submit="confirmarBaja">
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Cantidad a dar de baja *</label>
                    <input type="number" min="0.01" step="0.01" wire:model="bajaCantidad" class="{{ $inputClass }}">
                    @error('bajaCantidad') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror

                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1 mt-3">Notas (opcional)</label>
                    <textarea wire:model="bajaNotes" rows="2" class="{{ $inputClass }}"></textarea>

                    <div class="flex gap-3 mt-5">
                        <button type="submit" wire:loading.attr="disabled" wire:target="confirmarBaja" class="rounded-lg bg-gradient-to-r from-red-600 to-red-500 px-4 py-2 text-sm font-medium text-white shadow-md hover:from-red-700 hover:to-red-600 disabled:opacity-50">
                            Confirmar baja
                        </button>
                        <button type="button" wire:click="cancelarBaja" class="rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
