<div class="p-8 max-w-5xl mx-auto">
    <a href="{{ route('clients.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 mb-6">
        <x-heroicon-o-arrow-left class="w-4 h-4" />
        Clientes
    </a>
    <x-page-header title="Cuenta corriente · {{ $client->name }}" subtitle="Facturas y cobros del cliente" icon="users">
        @if ($whatsappReminder)
            <x-slot:actions>
                <a href="{{ $whatsappReminder }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-lg bg-green-500 hover:bg-green-400 px-3 py-2 text-sm font-semibold text-white shadow-sm">
                    <x-heroicon-o-chat-bubble-left-right class="w-4 h-4" /> Recordar por WhatsApp
                </a>
            </x-slot:actions>
        @endif
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
