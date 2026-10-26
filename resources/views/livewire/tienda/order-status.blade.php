<div class="container py-4 py-md-5">
    <div class="mx-auto" style="max-width: 560px;">
        <div class="tn-card p-4 p-md-5">
            <div class="text-center mb-4">
                @if($pedido->status === 'paid')
                    <div class="mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 64px; height: 64px; border-radius: 50%; background: #E6F6EC;">
                        <i class="fas fa-check" style="font-size: 1.6rem; color: #1B7A3D;"></i>
                    </div>
                    <h1 class="h4">Pedido confirmado</h1>
                @elseif($pedido->status === 'expired' || $pedido->status === 'cancelled')
                    <div class="mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 64px; height: 64px; border-radius: 50%; background: #FDECEC;">
                        <i class="fas fa-xmark" style="font-size: 1.6rem; color: #B3261E;"></i>
                    </div>
                    <h1 class="h4">Pedido cancelado</h1>
                @else
                    <div class="mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 64px; height: 64px; border-radius: 50%; background: #FEF3E2;">
                        <i class="fas fa-clock" style="font-size: 1.6rem; color: #966016;"></i>
                    </div>
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
                <div class="p-3 small" style="background: var(--tn-paper); border-radius: 10px; color: var(--tn-muted);">
                    Esta reserva venció sin confirmarse. Si todavía te interesa, hacé el pedido de nuevo.
                </div>
            @endif

            <div style="font-weight: 800; font-size: .85rem; color: var(--tn-muted);" class="mt-4 mb-2">Detalle</div>
            @foreach($pedido->invoice?->items ?? [] as $item)
                <div class="d-flex justify-content-between small py-2" style="border-bottom: 1px solid var(--tn-line);">
                    <span>{{ $item->quantity }}× {{ $item->description }}</span>
                    <span class="fw-bold">$ {{ number_format((float) $item->quantity * (float) $item->unit_price, 2, ',', '.') }}</span>
                </div>
            @endforeach
            <div class="d-flex justify-content-between pt-3">
                <span class="fw-bold">Total</span>
                <span class="tn-price" style="font-size: 1.5rem;">$ {{ number_format((float) ($pedido->invoice?->total ?? 0), 2, ',', '.') }}</span>
            </div>

            <div class="text-center mt-4">
                <a href="{{ route('tienda.catalog') }}" class="tn-back-link mb-0">
                    <i class="fas fa-arrow-left"></i> Volver a la tienda
                </a>
            </div>
        </div>
    </div>
</div>
