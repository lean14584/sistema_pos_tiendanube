<div class="p-8 max-w-5xl mx-auto">
    <a href="{{ route('providers.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 mb-6">
        <x-heroicon-o-arrow-left class="w-4 h-4" />
        Proveedores
    </a>
    <x-page-header title="Cuenta corriente · {{ $provider->name }}" subtitle="Compras y pagos al proveedor" icon="truck" />

    <x-current-account
        debit-label="Compra"
        payment-label="Pago"
        balance-owed-label="Les debemos"
        :debits="$debits"
        :payments="$payments"
        :payment-methods="$paymentMethods"
        :method="$method"
        :amount="$amount"
        :date="$date"
        :notes="$notes"
    />
</div>
