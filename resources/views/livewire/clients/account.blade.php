<div class="p-8 max-w-6xl mx-auto">
    <x-page-header title="Cuenta corriente · {{ $client->name }}" subtitle="Facturas y cobros del cliente" icon="users">
        <x-slot:actions>
            <a href="{{ route('clients.statement', $client) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-white/15 border border-white/25 px-3 py-2 text-sm font-medium text-white hover:bg-white/25">
                <x-heroicon-o-document-arrow-down class="w-4 h-4" /> Descargar PDF
            </a>
            <button wire:click="enviarPorEmail" wire:loading.attr="disabled" class="inline-flex items-center gap-1.5 rounded-lg bg-white/15 border border-white/25 px-3 py-2 text-sm font-medium text-white hover:bg-white/25 disabled:opacity-50">
                <x-heroicon-o-envelope class="w-4 h-4" /> Enviar por email
            </button>
            @if ($whatsappReminder)
                <a href="{{ $whatsappReminder }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-lg bg-green-500 hover:bg-green-400 px-3 py-2 text-sm font-semibold text-white shadow-sm">
                    <x-heroicon-o-chat-bubble-left-right class="w-4 h-4" /> Recordar por WhatsApp
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-current-account
        debit-label="Factura"
        payment-label="Cobro"
        balance-owed-label="Nos debe"
        :debits="$debits"
        :payments="$payments"
        :payment-methods="$paymentMethods"
        :method="$method"
        :amount="$amount"
        :date="$date"
        :notes="$notes"
        receipt-route="clients.recibo"
        :whatsapp-phone="$client->phone"
        :client-name="$client->name"
    />
</div>
