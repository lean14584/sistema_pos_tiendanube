<div class="p-8 max-w-4xl mx-auto">
    <a href="{{ route('invoices.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 mb-6">
        <x-heroicon-o-arrow-left class="w-4 h-4" />
        Facturas
    </a>

    <x-page-header title="{{ $invoice->number }}" subtitle="Emitida el {{ $invoice->issue_date->format('d/m/Y') }} · Vence el {{ $invoice->due_date->format('d/m/Y') }}" icon="document-text">
        <x-slot:actions>
            <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-white/20 text-white">
                {{ $invoice->tipo_comprobante_interno->label() }}
            </span>
            <x-status-badge :status="$invoice->effective_status" />
        </x-slot:actions>
    </x-page-header>

    <div class="flex gap-2 flex-wrap mb-8">
            @if ($invoice->esRemito())
                <a
                    href="{{ route('invoices.remito-pdf', $invoice) }}"
                    target="_blank"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-400 dark:hover:border-gray-600 hover:shadow-md active:scale-[0.98] transition-all"
                >
                    <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                    Remito PDF
                </a>
                <a
                    href="{{ route('invoices.remito-pdf', ['invoice' => $invoice, 'precios' => 0]) }}"
                    target="_blank"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-400 dark:hover:border-gray-600 hover:shadow-md active:scale-[0.98] transition-all"
                >
                    <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                    Sin precios
                </a>
                @if ($invoice->facturaGenerada() === null)
                    <a
                        href="{{ route('invoices.facturar-remito', $invoice) }}"
                        wire:navigate
                        class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600 active:scale-[0.98] transition-all"
                    >
                        <x-heroicon-o-document-text class="w-4 h-4" />
                        Facturar remito
                    </a>
                @endif
            @else
                <a
                    href="{{ route('invoices.pdf', $invoice) }}"
                    target="_blank"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-400 dark:hover:border-gray-600 hover:shadow-md active:scale-[0.98] transition-all"
                >
                    <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                    PDF
                </a>
            @endif
            <button
                wire:click="printTicket"
                wire:loading.attr="disabled"
                wire:target="printTicket"
                class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-400 dark:hover:border-gray-600 hover:shadow-md active:scale-[0.98] disabled:opacity-60 transition-all"
            >
                <x-heroicon-o-printer class="w-4 h-4" />
                <span wire:loading.remove wire:target="printTicket">Imprimir Ticket</span>
                <span wire:loading wire:target="printTicket">Imprimiendo...</span>
            </button>
            <button
                wire:click="enviarPorEmail"
                wire:loading.attr="disabled"
                wire:target="enviarPorEmail"
                @disabled(! $invoice->client?->email)
                title="{{ $invoice->client?->email ? 'Enviar la factura por email al cliente' : 'El cliente no tiene email cargado' }}"
                class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-400 dark:hover:border-gray-600 hover:shadow-md active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed transition-all"
            >
                <x-heroicon-o-envelope class="w-4 h-4" />
                <span wire:loading.remove wire:target="enviarPorEmail">Enviar email</span>
                <span wire:loading wire:target="enviarPorEmail">Enviando...</span>
            </button>
            @if ($this->whatsappUrl())
                <a
                    href="{{ $this->whatsappUrl() }}"
                    target="_blank"
                    class="inline-flex items-center gap-2 rounded-lg border border-emerald-300 dark:border-emerald-600/40 px-4 py-2 text-sm font-medium text-emerald-700 dark:text-emerald-400 shadow-sm hover:bg-emerald-50 dark:hover:bg-emerald-500/10 active:scale-[0.98] transition-all"
                >
                    <x-heroicon-o-chat-bubble-left-right class="w-4 h-4" />
                    WhatsApp
                </a>
            @endif
            @if ($mpConfigured && $invoice->status !== App\Enums\InvoiceStatus::Paid)
                <button
                    wire:click="startQrCharge"
                    wire:loading.attr="disabled"
                    wire:target="startQrCharge"
                    class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-sky-500 to-cyan-500 px-4 py-2 text-sm font-medium text-white shadow-md shadow-sky-500/30 hover:from-sky-600 hover:to-cyan-600 disabled:opacity-60 active:scale-[0.98] transition-all"
                >
                    <x-heroicon-o-qr-code class="w-4 h-4" />
                    <span wire:loading.remove wire:target="startQrCharge">Cobrar con QR</span>
                    <span wire:loading wire:target="startQrCharge">Generando...</span>
                </button>
            @endif
            @if ($invoice->status === App\Enums\InvoiceStatus::Draft && ! $invoice->isFiscal && $invoice->tipo_comprobante_interno->esFiscal())
                <button
                    x-on:click="confirmThen('¿Emitir la factura ' + @js($invoice->number) + ' a ARCA? Esta acción no se puede deshacer.', () => $wire.emitirAfip())"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600 disabled:opacity-60 transition-all"
                >
                    <x-heroicon-o-document-check class="w-4 h-4" />
                    Emitir a ARCA
                </button>
            @endif
            @if ($invoice->isFiscal && $invoice->related_invoice_id === null)
                <a
                    href="{{ route('invoices.nota-credito.create', $invoice) }}"
                    wire:navigate
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-400 dark:hover:border-gray-600 hover:shadow-md active:scale-[0.98] transition-all"
                >
                    <x-heroicon-o-receipt-refund class="w-4 h-4" />
                    Emitir Nota de Crédito
                </a>
            @endif
            @php $remitoYaFacturado = $invoice->esRemito() && $invoice->facturaGenerada() !== null; @endphp
            @unless ($invoice->isFiscal || $remitoYaFacturado)
                <a href="{{ route('invoices.edit', $invoice) }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-400 dark:hover:border-gray-600 hover:shadow-md active:scale-[0.98] transition-all">
                    <x-heroicon-o-pencil class="w-4 h-4" />
                    Editar
                </a>
                <button
                    x-on:click="confirmThen('¿Eliminar la factura ' + @js($invoice->number) + '?', () => $wire.delete())"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-700 px-3 py-2 text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors"
                >
                    <x-heroicon-o-trash class="w-4 h-4" />
                </button>
            @endunless
            @if ($remitoYaFacturado)
                <span class="inline-flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500 px-2">
                    <x-heroicon-o-lock-closed class="w-3.5 h-3.5" />
                    Ya facturado, no se puede editar ni eliminar
                </span>
            @endif
        </div>
    {{-- fin barra de acciones --}}

    @if ($afipError)
        <div class="mb-6 rounded-lg bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-400 px-4 py-3 text-sm">
            {{ $afipError }}
        </div>
    @endif

    @if ($invoice->related_invoice_id !== null)
        <div class="mb-6 rounded-lg bg-gray-50 dark:bg-gray-800/50 px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
            Corresponde a la factura
            <a href="{{ route('invoices.show', $invoice->relatedInvoice) }}" wire:navigate class="text-indigo-600 dark:text-indigo-400 font-medium hover:underline">
                {{ $invoice->relatedInvoice->number }}
            </a>
        </div>
    @endif

    @if ($invoice->remito_id !== null)
        <div class="mb-6 rounded-lg bg-gray-50 dark:bg-gray-800/50 px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
            Generada a partir del remito
            <a href="{{ route('invoices.show', $invoice->remito) }}" wire:navigate class="text-indigo-600 dark:text-indigo-400 font-medium hover:underline">
                {{ $invoice->remito->number }}
            </a>
        </div>
    @endif

    @if ($invoice->esRemito() && $invoice->facturaGenerada())
        <div class="mb-6 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-300">
            Este remito ya fue facturado con
            <a href="{{ route('invoices.show', $invoice->facturaGenerada()) }}" wire:navigate class="font-medium hover:underline">
                {{ $invoice->facturaGenerada()->number }}
            </a>
        </div>
    @endif

    @if ($invoice->isFiscal)
        <div class="mb-6 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 px-4 py-3">
            <p class="text-xs uppercase text-indigo-500 dark:text-indigo-400 mb-1">Comprobante fiscal ARCA</p>
            <p class="text-sm text-indigo-900 dark:text-indigo-300">
                {{ $invoice->tipo_comprobante->label() }} · Pto. Vta. {{ str_pad($invoice->punto_venta, 4, '0', STR_PAD_LEFT) }}
                N° {{ str_pad($invoice->numero_comprobante_afip, 8, '0', STR_PAD_LEFT) }}
            </p>
            <p class="text-sm text-indigo-900 dark:text-indigo-300">
                CAE {{ $invoice->cae }} · Vto. {{ $invoice->cae_vencimiento->format('d/m/Y') }}
            </p>
        </div>
    @endif

    @if ($invoice->related_invoice_id === null && $invoice->creditNotes->isNotEmpty())
        <div class="mb-6 rounded-lg bg-amber-50 dark:bg-amber-500/10 px-4 py-3 text-sm text-amber-800 dark:text-amber-400">
            Acreditado: ${{ number_format($invoice->credited_total, 2) }} de ${{ number_format($invoice->total, 2) }}
        </div>
    @endif

    <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 p-6 mb-6">
        <div class="flex items-center gap-3 mb-6">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Estado:</span>
            <div class="flex gap-1.5">
                @foreach ($statuses as $s)
                    <button
                        wire:click="setStatus('{{ $s->value }}')"
                        class="px-3 py-1 rounded-full text-xs font-medium transition-colors {{ $invoice->status === $s ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700' }}"
                    >
                        {{ $s->label() }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6 pb-6 border-b border-gray-100 dark:border-gray-800">
            <div>
                <p class="text-xs uppercase text-gray-400 dark:text-gray-500 mb-1">Facturado a</p>
                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $invoice->client->name }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $invoice->client->email }}</p>
                @if ($invoice->client->address)<p class="text-sm text-gray-500 dark:text-gray-400">{{ $invoice->client->address }}</p>@endif
                @if ($invoice->client->tax_id)<p class="text-sm text-gray-500 dark:text-gray-400">ID fiscal: {{ $invoice->client->tax_id }}</p>@endif
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
                @foreach ($invoice->items as $item)
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
                    <span>Neto gravado</span>
                    <span>${{ number_format($invoice->neto_gravado, 2) }}</span>
                </div>
                @if ($invoice->neto_exento > 0)
                    <div class="flex justify-between text-gray-600 dark:text-gray-400">
                        <span>Exento / no gravado</span>
                        <span>${{ number_format($invoice->neto_exento, 2) }}</span>
                    </div>
                @endif
                @foreach ($invoice->ivaPorAlicuota() as $linea)
                    <div class="flex justify-between text-gray-600 dark:text-gray-400">
                        <span>IVA {{ rtrim(rtrim(number_format($linea['tasa'], 2), '0'), '.') }}%</span>
                        <span>${{ number_format($linea['iva'], 2) }}</span>
                    </div>
                @endforeach
                <div class="flex justify-between font-semibold text-gray-900 dark:text-gray-100 text-base pt-2 border-t border-gray-200 dark:border-gray-800">
                    <span>Total</span>
                    <span>${{ number_format($invoice->total, 2) }}</span>
                </div>
            </div>
        </div>

        @if ($invoice->payments->isNotEmpty())
            <div class="mt-6 pt-6 border-t border-gray-100 dark:border-gray-800">
                <p class="text-xs uppercase text-gray-400 dark:text-gray-500 mb-2">Métodos de pago</p>
                <div class="space-y-1">
                    @foreach ($invoice->payments as $payment)
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">{{ $payment->method->label() }}</span>
                            <span class="text-gray-900 dark:text-gray-100 font-medium">${{ number_format($payment->amount, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($invoice->notes)
            <div class="mt-6 pt-6 border-t border-gray-100 dark:border-gray-800">
                <p class="text-xs uppercase text-gray-400 dark:text-gray-500 mb-1">Notas</p>
                <p class="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-wrap">{{ $invoice->notes }}</p>
            </div>
        @endif
    </div>

    {{-- Modal de cobro con QR de Mercado Pago --}}
    @if ($showQrModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/60" wire:click="{{ $qrState === 'waiting' ? 'cancelQrCharge' : 'closeQrModal' }}"></div>

            <div class="relative w-full max-w-sm rounded-2xl bg-white dark:bg-gray-900 shadow-2xl border border-gray-200 dark:border-gray-800 overflow-hidden">
                <div class="bg-gradient-to-r from-sky-500 to-cyan-500 px-6 py-4 text-white">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-qr-code class="w-5 h-5" />
                            <h2 class="font-semibold">Cobrar con Mercado Pago</h2>
                        </div>
                        <button wire:click="{{ $qrState === 'waiting' ? 'cancelQrCharge' : 'closeQrModal' }}" class="text-white/80 hover:text-white">
                            <x-heroicon-o-x-mark class="w-5 h-5" />
                        </button>
                    </div>
                    <p class="text-sm text-white/90 mt-1">Factura {{ $invoice->number }} · ${{ number_format($invoice->total, 2) }}</p>
                </div>

                <div class="p-6 text-center">
                    @if ($qrState === 'error')
                        <div class="py-6">
                            <x-heroicon-o-exclamation-triangle class="w-12 h-12 mx-auto text-red-500 mb-3" />
                            <p class="text-sm text-red-600 dark:text-red-400">{{ $qrError }}</p>
                        </div>
                    @elseif ($qrState === 'paid')
                        <div class="py-8">
                            <x-heroicon-o-check-circle class="w-16 h-16 mx-auto text-emerald-500 mb-3" />
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">¡Pago recibido!</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">La factura quedó marcada como pagada.</p>
                            <button wire:click="closeQrModal" class="mt-5 inline-flex items-center gap-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 px-4 py-2 text-sm font-medium text-white transition-colors">
                                Listo
                            </button>
                        </div>
                    @else
                        {{-- waiting: mostrar el QR y hacer polling --}}
                        <div wire:poll.{{ $pollSeconds }}s="pollQr">
                            @if ($qrImage)
                                <div class="inline-block rounded-xl border border-gray-200 dark:border-gray-700 bg-white p-3">
                                    <img src="{{ $qrImage }}" alt="QR de pago" class="w-52 h-52 object-contain">
                                </div>
                            @else
                                <div class="w-52 h-52 mx-auto rounded-xl border border-dashed border-gray-300 dark:border-gray-700 flex items-center justify-center text-xs text-gray-400 p-4">
                                    Usá el QR fijo pegado en la caja: ya tiene cargado el monto de esta factura.
                                </div>
                            @endif

                            <div class="mt-4 flex items-center justify-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                                <svg class="animate-spin w-4 h-4 text-sky-500" viewBox="0 0 24 24" fill="none">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                </svg>
                                Esperando el pago del cliente...
                            </div>

                            <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">El cliente escanea el QR, paga, y la factura se marca sola.</p>

                            <button wire:click="cancelQrCharge" class="mt-5 inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                Cancelar cobro
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
