<div class="container py-4 py-md-5">
    <h1 class="h3 mb-4">Tu carrito</h1>

    @if($lines->isEmpty())
        <div class="tn-card text-center py-5">
            <i class="fas fa-cart-shopping mb-3" style="font-size: 2.2rem; color: var(--tn-line);"></i>
            <p class="fw-bold mb-1">Tu carrito está vacío</p>
            <p class="text-muted small mb-3">Agregá productos del catálogo para arrancar un pedido.</p>
            <a href="{{ route('tienda.catalog') }}" class="btn btn-tn-primary">Ver catálogo</a>
        </div>
    @else
        <div class="row g-4 g-md-5">
            <div class="col-12 col-md-8">
                <div class="tn-card">
                    @foreach($lines as $line)
                        <div class="row align-items-center p-3" style="{{ ! $loop->last ? 'border-bottom: 1px solid var(--tn-line);' : '' }}" wire:key="cart-line-{{ $line['product']->id }}">
                            <div class="col-3 col-md-2">
                                <div class="d-flex align-items-center justify-content-center" style="aspect-ratio: 1; background: var(--tn-paper); border-radius: 10px;">
                                    @if($line['product']->imageUrl())
                                        <img src="{{ $line['product']->imageUrl() }}" alt="{{ $line['product']->name }}" style="max-width: 80%; max-height: 80%; object-fit: contain;">
                                    @else
                                        <i class="fas fa-basket-shopping" style="color: var(--tn-line);"></i>
                                    @endif
                                </div>
                            </div>
                            <div class="col-9 col-md-4">
                                <a href="{{ route('tienda.product', $line['product']) }}" class="text-decoration-none fw-bold" style="color: var(--tn-ink);">
                                    {{ $line['product']->name }}
                                </a>
                                <div class="text-muted small">$ {{ number_format((float) $line['product']->price, 2, ',', '.') }} c/u</div>
                                @if($line['quantity'] > $line['stock'])
                                    <div class="small mt-1" style="color: #B3261E;">Solo quedan {{ $line['stock'] }} disponibles</div>
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
                                <button type="button" wire:click="remove({{ $line['product']->id }})" class="btn btn-sm btn-link p-0" style="color: var(--tn-muted);">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <a href="{{ route('tienda.catalog') }}" class="tn-back-link mt-4">
                    <i class="fas fa-arrow-left"></i> Seguir comprando
                </a>
            </div>

            <div class="col-12 col-md-4">
                <div class="tn-card p-3 p-md-4">
                    <div style="font-weight: 800; font-size: .85rem; color: var(--tn-muted);" class="mb-3">Resumen</div>
                    <div class="d-flex justify-content-between pb-3" style="border-bottom: 1px solid var(--tn-line);">
                        <span class="text-muted">Productos</span>
                        <span class="fw-bold">$ {{ number_format($total, 2, ',', '.') }}</span>
                    </div>
                    <div class="d-flex justify-content-between pt-3 mb-4">
                        <span class="fw-bold">Total</span>
                        <span class="tn-price" style="font-size: 1.6rem;">$ {{ number_format($total, 2, ',', '.') }}</span>
                    </div>
                    <a href="{{ route('tienda.checkout') }}" class="btn btn-tn-primary w-100">
                        Finalizar compra <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    @endif
</div>
