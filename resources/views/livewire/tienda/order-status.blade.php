<div class="container py-4 py-md-5">
    <div class="mx-auto" style="max-width: 560px;">
        <div class="text-center mb-4">
            @if($pedido->status === 'paid')
                <i class="fas fa-check-circle mb-2" style="font-size: 2.2rem; color: var(--market);"></i>
                <h1 class="h4">Pedido confirmado</h1>
            @elseif($pedido->status === 'expired' || $pedido->status === 'cancelled')
                <i class="fas fa-circle-xmark mb-2" style="font-size: 2.2rem; color: var(--stamp);"></i>
                <h1 class="h4">Pedido cancelado</h1>
            @else
                <i class="fas fa-clock mb-2" style="font-size: 2.2rem; color: var(--saffron-dark);"></i>
                <h1 class="h4">Pedido recibido</h1>
            @endif
            <p class="text-muted small mb-0">Pedido #{{ $pedido->id }} · {{ $pedido->created_at->format('d/m/Y H:i') }}</p>
        </div>

        @if($pedido->status === 'pending_payment' && $pedido->mp_preference_id)
            <div class="alert-tn-info p-3 small">
                Estamos confirmando tu pago con Mercado Pago. En unos minutos te llega la confirmación a
                <strong>{{ $pedido->customer_email }}</strong>. Guardamos tu stock reservado hasta {{ $pedido->expires_at?->format('d/m H:i') }}.
            </div>
        @elseif($pedido->status === 'pending_payment')
            <div class="alert-tn-info p-3 small">
                Te vamos a contactar a <strong>{{ $pedido->customer_email }}</strong> para coordinar el pago y la entrega.
                Guardamos tu stock reservado hasta {{ $pedido->expires_at?->format('d/m H:i') }}.
            </div>
        @elseif($pedido->status === 'expired' || $pedido->status === 'cancelled')
            <div class="p-3 small" style="background: #EFEAE0; border-radius: 4px; color: var(--muted);">
                Esta reserva venció sin confirmarse. Si todavía te interesa, hacé el pedido de nuevo.
            </div>
        @endif

        <div style="font-weight: 600; font-size: .85rem; color: var(--muted);" class="mt-4 mb-2">Detalle</div>
        <div style="border-top: 1px solid var(--rule);">
            @foreach($pedido->invoice?->items ?? [] as $item)
                <div class="d-flex justify-content-between small py-2" style="border-bottom: 1px solid var(--rule);">
                    <span>{{ $item->quantity }}× {{ $item->description }}</span>
                    <span class="fw-bold">$ {{ number_format((float) $item->quantity * (float) $item->unit_price, 2, ',', '.') }}</span>
                </div>
            @endforeach
        </div>
        <div class="d-flex justify-content-between pt-3">
            <span class="fw-bold">Total</span>
            <span class="tn-price" style="font-size: 1.4rem;">$ {{ number_format((float) ($pedido->invoice?->total ?? 0), 2, ',', '.') }}</span>
        </div>

        <div class="text-center mt-4">
            <a href="{{ route('tienda.catalog') }}" class="tn-back-link mb-0">
                <i class="fas fa-arrow-left"></i> Volver a la tienda
            </a>
        </div>
    </div>
</div>
