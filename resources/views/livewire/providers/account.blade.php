<div class="p-8 max-w-6xl mx-auto">
    <x-page-header title="Cuenta corriente · {{ $provider->name }}" subtitle="Compras y pagos al proveedor" icon="truck">
        <x-slot:actions>
            <a href="{{ route('providers.statement', $provider) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-white/15 border border-white/25 px-3 py-2 text-sm font-medium text-white hover:bg-white/25">
                <x-heroicon-o-document-arrow-down class="w-4 h-4" /> Descargar PDF
            </a>
            <button wire:click="enviarPorEmail" wire:loading.attr="disabled" class="inline-flex items-center gap-1.5 rounded-lg bg-white/15 border border-white/25 px-3 py-2 text-sm font-medium text-white hover:bg-white/25 disabled:opacity-50">
                <x-heroicon-o-envelope class="w-4 h-4" /> Enviar por email
            </button>
        </x-slot:actions>
    </x-page-header>

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
