<div class="container py-4 py-md-5">
    <div class="bg-white rounded-4 shadow-sm p-4 p-md-5 mx-auto" style="max-width: 640px;">
        <div class="text-center mb-4">
            @if($pedido->status === 'paid')
                <i class="fas fa-check-circle text-success fs-1 mb-2"></i>
                <h1 class="h4 fw-bold">¡Pedido confirmado!</h1>
            @elseif($pedido->status === 'expired' || $pedido->status === 'cancelled')
                <i class="fas fa-times-circle text-danger fs-1 mb-2"></i>
                <h1 class="h4 fw-bold">Pedido cancelado</h1>
            @else
                <i class="fas fa-clock text-warning fs-1 mb-2"></i>
                <h1 class="h4 fw-bold">Pedido recibido</h1>
            @endif
            <p class="text-muted small mb-0">Pedido #{{ $pedido->id }} — {{ $pedido->created_at->format('d/m/Y H:i') }}</p>
        </div>

        @if($pedido->status === 'pending_payment' && $pedido->mp_preference_id)
            <div class="alert alert-info small">
                <i class="fas fa-info-circle me-1"></i>
                Estamos confirmando tu pago con Mercado Pago. En unos minutos te llega la confirmación a
                <strong>{{ $pedido->customer_email }}</strong>. Guardamos tu stock reservado hasta {{ $pedido->expires_at?->format('d/m H:i') }}.
            </div>
        @elseif($pedido->status === 'pending_payment')
            <div class="alert alert-info small">
                <i class="fas fa-info-circle me-1"></i>
                Te vamos a contactar a <strong>{{ $pedido->customer_email }}</strong> para coordinar el pago y la entrega.
                Guardamos tu stock reservado hasta {{ $pedido->expires_at?->format('d/m H:i') }}.
            </div>
        @elseif($pedido->status === 'expired' || $pedido->status === 'cancelled')
            <div class="alert alert-secondary small">
                Esta reserva venció sin confirmarse. Si todavía te interesa, hacé el pedido de nuevo.
            </div>
        @endif

        <h2 class="h6 fw-bold text-uppercase text-muted mt-4 mb-3">Detalle</h2>
        @foreach($pedido->invoice?->items ?? [] as $item)
            <div class="d-flex justify-content-between small py-2 border-bottom">
                <span>{{ $item->quantity }}x {{ $item->description }}</span>
                <span class="fw-bold">$ {{ number_format((float) $item->quantity * (float) $item->unit_price, 2, ',', '.') }}</span>
            </div>
        @endforeach
        <div class="d-flex justify-content-between pt-3">
            <span class="fw-bold">Total</span>
            <span class="tn-price">$ {{ number_format((float) ($pedido->invoice?->total ?? 0), 2, ',', '.') }}</span>
        </div>

        <div class="text-center mt-4">
            <a href="{{ route('tienda.catalog') }}" class="tn-back-link mb-0">
                <i class="fas fa-arrow-left"></i> Volver a la tienda
            </a>
        </div>
    </div>
</div>
