<div class="container py-4 py-md-5">
    <a href="{{ route('tienda.catalog') }}" class="tn-back-link">
        <i class="fas fa-arrow-left"></i> Volver al catálogo
    </a>

    <div class="row g-4 g-md-5 bg-white rounded-4 shadow-sm p-3 p-md-4">
        <div class="col-12 col-md-6">
            <div class="tn-img-container" style="height: 360px; border-radius: 12px;">
                @if($product->imageUrl())
                    <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}">
                @else
                    <i class="fas fa-box-open text-muted" style="font-size: 4rem;"></i>
                @endif
            </div>
        </div>
        <div class="col-12 col-md-6 d-flex flex-column justify-content-center">
            <span class="text-muted small text-uppercase fw-bold mb-1" style="letter-spacing:.5px;">
                {{ $product->category?->name ?? 'General' }}
            </span>
            <h1 class="h3 fw-bold mb-3">{{ $product->name }}</h1>

            <div class="mb-3">
                <span class="tn-price" style="font-size: 2rem;">$ {{ number_format((float) $product->price, 2, ',', '.') }}</span>
            </div>

            @if($product->description)
                <p class="text-muted">{{ $product->description }}</p>
            @endif

            <div class="mb-3">
                @if($stock > 0)
                    <span class="badge bg-success-subtle text-success-emphasis rounded-pill px-3 py-2">
                        <i class="fas fa-check-circle me-1"></i> Hay stock disponible
                    </span>
                @else
                    <span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill px-3 py-2">
                        Sin stock por el momento
                    </span>
                @endif
            </div>

            @if($stock > 0)
                @if($justAdded)
                    <div class="alert alert-success d-flex align-items-center justify-content-between py-2 px-3 mb-0" role="alert">
                        <span><i class="fas fa-check-circle me-1"></i> Agregado al carrito.</span>
                        <a href="{{ route('tienda.cart') }}" class="btn btn-sm btn-success rounded-pill">Ver carrito</a>
                    </div>
                @else
                    <form wire:submit="addToCart" class="d-flex align-items-center gap-2">
                        <input type="number" wire:model="quantity" min="1" max="{{ $stock }}" class="form-control" style="width: 90px;">
                        <button type="submit" class="btn btn-dark rounded-pill px-4 flex-grow-1">
                            <i class="fas fa-shopping-cart me-1"></i> Agregar al carrito
                        </button>
                    </form>
                @endif
            @endif
        </div>
    </div>
</div>
