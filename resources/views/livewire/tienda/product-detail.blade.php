<div class="container py-4 py-md-5">
    <a href="{{ route('tienda.catalog') }}" class="tn-back-link">
        <i class="fas fa-arrow-left"></i> Volver al catálogo
    </a>

    <div class="row g-4 g-md-5">
        <div class="col-12 col-md-6">
            <div class="d-flex align-items-center justify-content-center bg-white" style="aspect-ratio: 1; border: 1px solid var(--rule);">
                @if($product->imageUrl())
                    <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" style="max-width: 80%; max-height: 80%; object-fit: contain;">
                @else
                    <i class="fas fa-basket-shopping" style="font-size: 4rem; color: var(--rule);"></i>
                @endif
            </div>
        </div>
        <div class="col-12 col-md-6 d-flex flex-column justify-content-center">
            <div style="font-size: .8rem; color: var(--muted);">{{ $product->category?->name ?? 'general' }}</div>
            <h1 class="h3 mt-1 mb-3">{{ $product->name }}</h1>

            <div class="tn-price mb-3" style="font-size: 2.2rem;">$ {{ number_format((float) $product->price, 2, ',', '.') }}</div>

            @if($product->description)
                <p class="text-muted mb-3">{{ $product->description }}</p>
            @endif

            <div class="mb-4">
                @if($stock > 0)
                    <span class="tn-badge-ok"><i class="fas fa-check me-1"></i> Hay stock</span>
                @else
                    <span class="tn-badge-off">Sin stock por ahora</span>
                @endif
            </div>

            @if($stock > 0)
                @if($justAdded)
                    <div class="alert-tn-info p-3 d-flex align-items-center justify-content-between">
                        <span>Lo agregamos al carrito.</span>
                        <a href="{{ route('tienda.cart') }}" class="btn btn-tn-primary btn-sm">Ver carrito</a>
                    </div>
                @else
                    <form wire:submit="addToCart" class="d-flex align-items-center gap-2">
                        <input type="number" wire:model="quantity" min="1" max="{{ $stock }}" class="form-control" style="width: 90px;">
                        <button type="submit" class="btn btn-tn-primary flex-grow-1">
                            Agregar al carrito
                        </button>
                    </form>
                @endif
            @endif
        </div>
    </div>
</div>
