<div class="container py-4 py-md-5">
    <h1 class="h3 mb-4">Tu carrito</h1>

    @if($lines->isEmpty())
        <div class="text-center py-5" style="border: 1px solid var(--rule); border-radius: 6px;">
            <p class="fw-bold mb-1">Tu carrito está vacío</p>
            <p class="text-muted small mb-3">Agregá productos del catálogo para arrancar un pedido.</p>
            <a href="{{ route('tienda.catalog') }}" class="btn btn-tn-primary">Ver catálogo</a>
        </div>
    @else
        <div style="border-top: 1px solid var(--rule);">
            @foreach($lines as $line)
                <div class="row align-items-center py-3" style="border-bottom: 1px solid var(--rule);" wire:key="cart-line-{{ $line['product']->id }}">
                    <div class="col-3 col-md-2">
                        <div class="d-flex align-items-center justify-content-center bg-white" style="aspect-ratio: 1;">
                            @if($line['product']->imageUrl())
                                <img src="{{ $line['product']->imageUrl() }}" alt="{{ $line['product']->name }}" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                            @else
                                <i class="fas fa-basket-shopping" style="color: var(--rule);"></i>
                            @endif
                        </div>
                    </div>
                    <div class="col-9 col-md-4">
                        <a href="{{ route('tienda.product', $line['product']) }}" class="text-decoration-none fw-bold" style="color: var(--ink);">
                            {{ $line['product']->name }}
                        </a>
                        <div class="text-muted small">$ {{ number_format((float) $line['product']->price, 2, ',', '.') }} c/u</div>
                        @if($line['quantity'] > $line['stock'])
                            <div class="small mt-1" style="color: var(--stamp);">Solo quedan {{ $line['stock'] }} disponibles</div>
                        @endif
                    </div>
                    <div class="col-6 col-md-3 mt-2 mt-md-0">
                        <input type="number" min="1" max="{{ max($line['stock'], 1) }}"
                               value="{{ $line['quantity'] }}"
                               wire:change="updateQuantity({{ $line['product']->id }}, $event.target.value)"
                               class="form-control form-control-sm" style="width: 80px;">
                    </div>
                    <div class="col-4 col-md-2 mt-2 mt-md-0 tn-price text-end">
                        $ {{ number_format($line['subtotal'], 2, ',', '.') }}
                    </div>
                    <div class="col-2 col-md-1 mt-2 mt-md-0 text-end">
                        <button type="button" wire:click="remove({{ $line['product']->id }})" class="btn btn-sm btn-link p-0" style="color: var(--muted);">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="d-flex justify-content-between align-items-center pt-4">
            <a href="{{ route('tienda.catalog') }}" class="tn-back-link mb-0">
                <i class="fas fa-arrow-left"></i> Seguir comprando
            </a>
            <div class="text-end">
                <div class="text-muted small">Total</div>
                <div class="tn-price" style="font-size: 1.7rem;">$ {{ number_format($total, 2, ',', '.') }}</div>
            </div>
        </div>

        <div class="text-end mt-3">
            <a href="{{ route('tienda.checkout') }}" class="btn btn-tn-primary px-4">
                Finalizar compra
            </a>
        </div>
    @endif
</div>
