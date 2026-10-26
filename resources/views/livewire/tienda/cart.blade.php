<div class="container py-4 py-md-5">
    <h1 class="h3 fw-bold mb-4"><i class="fas fa-shopping-cart me-2"></i> Tu carrito</h1>

    @if($lines->isEmpty())
        <div class="bg-white rounded-4 shadow-sm p-5 text-center">
            <i class="fas fa-box-open text-muted fs-1 mb-3"></i>
            <h5 class="text-secondary fw-bold">Tu carrito está vacío</h5>
            <a href="{{ route('tienda.catalog') }}" class="btn btn-dark rounded-pill px-4 mt-3">Ver catálogo</a>
        </div>
    @else
        <div class="bg-white rounded-4 shadow-sm p-3 p-md-4">
            @foreach($lines as $line)
                <div class="row align-items-center py-3 {{ ! $loop->last ? 'border-bottom' : '' }}" wire:key="cart-line-{{ $line['product']->id }}">
                    <div class="col-3 col-md-2">
                        <div class="tn-img-container" style="height: 70px; border-radius: 8px;">
                            @if($line['product']->imageUrl())
                                <img src="{{ $line['product']->imageUrl() }}" alt="{{ $line['product']->name }}">
                            @else
                                <i class="fas fa-box-open text-muted"></i>
                            @endif
                        </div>
                    </div>
                    <div class="col-9 col-md-4">
                        <a href="{{ route('tienda.product', $line['product']) }}" class="text-decoration-none text-dark fw-bold">
                            {{ $line['product']->name }}
                        </a>
                        <div class="text-muted small">$ {{ number_format((float) $line['product']->price, 2, ',', '.') }} c/u</div>
                        @if($line['quantity'] > $line['stock'])
                            <div class="text-danger small mt-1">Solo quedan {{ $line['stock'] }} disponibles</div>
                        @endif
                    </div>
                    <div class="col-6 col-md-3 mt-2 mt-md-0">
                        <input type="number" min="1" max="{{ max($line['stock'], 1) }}"
                               value="{{ $line['quantity'] }}"
                               wire:change="updateQuantity({{ $line['product']->id }}, $event.target.value)"
                               class="form-control form-control-sm" style="width: 80px;">
                    </div>
                    <div class="col-4 col-md-2 mt-2 mt-md-0 fw-bold text-end">
                        $ {{ number_format($line['subtotal'], 2, ',', '.') }}
                    </div>
                    <div class="col-2 col-md-1 mt-2 mt-md-0 text-end">
                        <button type="button" wire:click="remove({{ $line['product']->id }})" class="btn btn-sm btn-link text-danger">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            @endforeach

            <div class="d-flex justify-content-between align-items-center pt-4 mt-2 border-top">
                <a href="{{ route('tienda.catalog') }}" class="tn-back-link mb-0">
                    <i class="fas fa-arrow-left"></i> Seguir comprando
                </a>
                <div class="text-end">
                    <div class="text-muted small">Total</div>
                    <div class="tn-price" style="font-size: 1.6rem;">$ {{ number_format($total, 2, ',', '.') }}</div>
                </div>
            </div>

            <div class="text-end mt-3">
                <a href="{{ route('tienda.checkout') }}" class="btn btn-dark rounded-pill px-4">
                    Finalizar compra <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    @endif
</div>
