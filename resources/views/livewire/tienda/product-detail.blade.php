<div>
<div class="container py-4 py-md-5">
    <nav class="tn-breadcrumb">
        <a href="{{ route('tienda.catalog') }}">Catálogo</a>
        @if($product->category)
            <span class="sep">/</span>
            <a href="{{ route('tienda.catalog', ['categoria' => $product->category->id]) }}">{{ $product->category->name }}</a>
        @endif
        <span class="sep">/</span>
        <span>{{ $product->name }}</span>
    </nav>

    <a href="{{ route('tienda.catalog') }}" class="tn-back-link">
        <i class="fas fa-arrow-left"></i> Volver al catálogo
    </a>

    @php
        $tnPh = [
            ['bg' => 'linear-gradient(135deg, #FDE8E8, #FBD4D4)', 'icon' => 'fa-cookie-bite'],
            ['bg' => 'linear-gradient(135deg, #E8F3FD, #D2E6FB)', 'icon' => 'fa-wine-bottle'],
            ['bg' => 'linear-gradient(135deg, #E9F9EE, #D2F0DC)', 'icon' => 'fa-leaf'],
            ['bg' => 'linear-gradient(135deg, #FDF3E3, #FBE6C0)', 'icon' => 'fa-mug-hot'],
            ['bg' => 'linear-gradient(135deg, #F3E9FD, #E5D3FB)', 'icon' => 'fa-soap'],
            ['bg' => 'linear-gradient(135deg, #E9FDFB, #CFF5EF)', 'icon' => 'fa-box-open'],
        ];
        $ph = $tnPh[$product->id % count($tnPh)];
    @endphp
    <div class="tn-card p-3 p-md-4 {{ $stock > 0 && ! $justAdded ? 'tn-sticky-cta-pad' : '' }}">
        <div class="row g-4 g-md-5">
            <div class="col-12 col-md-6">
                <div class="d-flex align-items-center justify-content-center" style="aspect-ratio: 1; background: var(--tn-paper); border-radius: 12px; overflow: hidden;">
                    @if($product->imageUrl())
                        <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" style="max-width: 80%; max-height: 80%; object-fit: contain;">
                    @else
                        <div class="tn-media-ph tn-media-ph-lg" style="background: {{ $ph['bg'] }};"><i class="fas {{ $ph['icon'] }}"></i></div>
                    @endif
                </div>
            </div>
            <div class="col-12 col-md-6 d-flex flex-column justify-content-center">
                <div style="font-size: .8rem; color: var(--tn-muted); font-weight: 600;">{{ $product->category?->name ?? 'General' }}</div>
                <h1 class="h3 mt-1 mb-3">{{ $product->name }}</h1>

                <div class="tn-price mb-3" style="font-size: 2.4rem;"><span class="tn-price-currency">$</span>{{ number_format((float) $product->price, 2, ',', '.') }}</div>

                @if($product->description)
                    <p class="text-muted mb-3">{{ $product->description }}</p>
                @endif

                <div class="mb-4">
                    @if($stock <= 0)
                        <span class="tn-badge-off">Sin stock por ahora</span>
                    @elseif($stock <= 10)
                        <span class="tn-badge-warn"><i class="fas fa-triangle-exclamation me-1"></i> Quedan {{ $stock }} unidades</span>
                    @else
                        <span class="tn-badge-ok"><i class="fas fa-check me-1"></i> Quedan {{ $stock }} unidades</span>
                    @endif
                </div>

                @if($stock > 0)
                    @if($justAdded)
                        <div class="alert-tn-info p-3 d-flex align-items-center justify-content-between">
                            <span><i class="fas fa-circle-check me-1"></i> Lo agregamos al carrito.</span>
                            <a href="{{ route('tienda.cart') }}" class="btn btn-tn-primary btn-sm">Ver carrito</a>
                        </div>
                    @else
                        <form wire:submit="addToCart" class="d-flex align-items-center gap-2">
                            <div class="tn-stepper">
                                <button type="button" wire:click="decrementQuantity" {{ $quantity <= 1 ? 'disabled' : '' }}>−</button>
                                <input type="number" wire:model="quantity" min="1" max="{{ $stock }}" readonly>
                                <button type="button" wire:click="incrementQuantity" {{ $quantity >= $stock ? 'disabled' : '' }}>+</button>
                            </div>
                            <button type="submit" class="btn btn-tn-primary flex-grow-1">
                                <i class="fas fa-cart-plus me-1"></i> Agregar al carrito
                            </button>
                        </form>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>

@if($stock > 0 && ! $justAdded)
    <div class="tn-sticky-cta">
        <div class="tn-price" style="font-size: 1.3rem;"><span class="tn-price-currency">$</span>{{ number_format((float) $product->price, 2, ',', '.') }}</div>
        <div class="tn-stepper">
            <button type="button" wire:click="decrementQuantity" {{ $quantity <= 1 ? 'disabled' : '' }}>−</button>
            <input type="number" wire:model="quantity" min="1" max="{{ $stock }}" readonly>
            <button type="button" wire:click="incrementQuantity" {{ $quantity >= $stock ? 'disabled' : '' }}>+</button>
        </div>
        <button type="button" wire:click="addToCart" class="btn btn-tn-primary">
            <i class="fas fa-cart-plus"></i>
        </button>
    </div>
@endif
</div>
