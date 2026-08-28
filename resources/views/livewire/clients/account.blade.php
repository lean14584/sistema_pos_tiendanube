<div class="p-8 max-w-5xl mx-auto">
    <a href="{{ route('clients.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 mb-6">
        <x-heroicon-o-arrow-left class="w-4 h-4" />
        Clientes
    </a>
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

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 px-4 py-2.5 text-sm text-emerald-700 dark:text-emerald-300">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 px-4 py-2.5 text-sm text-red-800 dark:text-red-400">{{ session('error') }}</div>
    @endif

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
