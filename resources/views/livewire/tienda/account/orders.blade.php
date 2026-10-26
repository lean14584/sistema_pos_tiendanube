<div class="container py-4 py-md-5">
    <h1 class="h3 mb-4">Mis pedidos</h1>

    @if($pedidos->isEmpty())
        <div class="text-center py-5" style="border: 1px solid var(--rule); border-radius: 6px;">
            <p class="fw-bold mb-1">Todavía no hiciste ningún pedido</p>
            <a href="{{ route('tienda.catalog') }}" class="btn btn-tn-primary mt-2">Ver catálogo</a>
        </div>
    @else
        <div style="border-top: 1px solid var(--rule);">
            @foreach($pedidos as $pedido)
                <a href="{{ route('tienda.order-status', $pedido->token) }}" class="d-flex justify-content-between align-items-center py-3 text-decoration-none" style="color: var(--ink); border-bottom: 1px solid var(--rule);">
                    <div>
                        <div class="fw-bold">Pedido #{{ $pedido->id }}</div>
                        <div class="text-muted small">{{ $pedido->created_at->format('d/m/Y H:i') }}</div>
                    </div>
                    <div class="text-end">
                        <div class="tn-price">$ {{ number_format((float) ($pedido->invoice?->total ?? 0), 2, ',', '.') }}</div>
                        @php
                            $estado = match($pedido->status) {
                                'paid' => ['Pagado', 'tn-badge-ok'],
                                'expired' => ['Vencido', 'tn-badge-off'],
                                'cancelled' => ['Cancelado', 'tn-badge-off'],
                                default => ['Pendiente', 'tn-badge-warn'],
                            };
                        @endphp
                        <span class="{{ $estado[1] }}" style="font-size: .72rem; padding: .2rem .55rem;">{{ $estado[0] }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
