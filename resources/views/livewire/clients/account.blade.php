<div class="p-8 max-w-5xl mx-auto">
    <a href="{{ route('clients.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 mb-6">
        <x-heroicon-o-arrow-left class="w-4 h-4" />
        Clientes
    </a>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100 mb-1">Cuenta corriente · {{ $client->name }}</h1>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">Facturas y cobros del cliente</p>

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
    />
</div>
