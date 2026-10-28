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
    <div class="{{ $stock > 0 && ! $justAdded ? 'tn-sticky-cta-pad' : '' }}">
        <div class="tn-card p-3 p-md-4">
            <div class="row g-4 g-md-5" id="tnPdRow" style="position: relative;">
                {{-- Panel izquierdo: imagen --}}
                <div class="col-12 col-md-7">
                    <div class="d-flex align-items-center justify-content-center" style="aspect-ratio: 1;"
                        @if($product->imageUrl())
                            onmouseenter="tnZoomShow(this)" onmouseleave="tnZoomHide(this)" onmousemove="tnZoomMove(event, this)"
                        @endif
                    >
                        @if($product->imageUrl())
                            <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" style="max-width: 85%; max-height: 85%; object-fit: contain;">
                        @else
                            <div class="tn-media-ph tn-media-ph-lg" style="background: {{ $ph['bg'] }}; width: 100%; height: 100%; border-radius: 12px;"><i class="fas {{ $ph['icon'] }}"></i></div>
                        @endif
                    </div>
                </div>

                {{-- Panel derecho: recuadro de línea fina con categoría, nombre, stock, y abajo precio/cantidad/botones --}}
                <div class="col-12 col-md-5">
                    <div class="p-3 p-md-4 h-100" id="tnPdInfoCard" style="border: 1px solid var(--tn-line); border-radius: 12px;">
                        <div style="font-size: .8rem; color: var(--tn-muted); font-weight: 600;">{{ $product->category?->name ?? 'General' }}</div>
                        <h1 class="h3 mt-1 mb-2">{{ $product->name }}</h1>
                        <div class="d-flex align-items-center gap-2 mb-3">
                            @if($stock <= 0)
                                <span class="tn-badge-off">Sin stock por ahora</span>
                            @elseif($stock <= 10)
                                <span class="tn-badge-warn"><i class="fas fa-triangle-exclamation me-1"></i> Quedan {{ $stock }} unidades</span>
                            @else
                                <span class="tn-badge-ok"><i class="fas fa-check me-1"></i> Quedan {{ $stock }} unidades</span>
                            @endif
                        </div>
                        @if($product->description)
                            <p class="text-muted">{{ $product->description }}</p>
                        @endif

                        <div class="mt-4 pt-4" style="border-top: 1px solid var(--tn-line);">
                            <div class="tn-price mb-1" style="font-size: 2.2rem;"><span class="tn-price-currency">$</span>{{ number_format((float) $product->price, 2, ',', '.') }}</div>
                            @if((float) $product->price >= 3000)
                                <div class="tn-installments mb-3">Hasta 3x $ {{ number_format((float) $product->price / 3, 2, ',', '.') }}</div>
                            @endif

                            @if($stock > 0)
                                @if($justAdded)
                                    <div class="alert-tn-info p-3 mb-0 mt-3">
                                        <div class="mb-2"><i class="fas fa-circle-check me-1"></i> Lo agregamos al carrito.</div>
                                        <a href="{{ route('tienda.cart') }}" class="btn btn-tn-primary btn-sm w-100">Ver carrito</a>
                                    </div>
                                @else
                                    <div class="mt-3" style="max-width: 320px;">
                                        <label class="form-label small mb-1">Cantidad</label>
                                        <div class="tn-stepper mb-3 w-100">
                                            <button type="button" wire:click="decrementQuantity" {{ $quantity <= 1 ? 'disabled' : '' }}>−</button>
                                            <input type="number" wire:model="quantity" min="1" max="{{ $stock }}" readonly style="flex: 1;">
                                            <button type="button" wire:click="incrementQuantity" {{ $quantity >= $stock ? 'disabled' : '' }}>+</button>
                                        </div>
                                        <button type="button" wire:click="buyNow" class="btn btn-tn-primary w-100 mb-2">
                                            Comprar ahora
                                        </button>
                                        <button type="button" wire:click="addToCart" class="btn btn-tn-soft w-100">
                                            <i class="fas fa-cart-plus me-1"></i> Agregar al carrito
                                        </button>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>

                @if($product->imageUrl())
                    <div class="tn-zoom-pane" style="background-image: url('{{ $product->imageUrl() }}');"></div>
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

<script>
    // El panel de zoom se calca exacto sobre la card de categoría/precio
    // (mismo top/left/width/height), no un recuadro de tamaño fijo propio
    // — así nunca se sale de ese recuadro, solo lo "reemplaza" visualmente
    // mientras se pasa el mouse por la foto.
    function tnZoomPositionPane(el) {
        var row = el.closest('#tnPdRow');
        var pane = row ? row.querySelector('.tn-zoom-pane') : null;
        var infoCard = row ? row.querySelector('#tnPdInfoCard') : null;
        if (!pane || !infoCard) return null;

        var rowRect = row.getBoundingClientRect();
        var infoRect = infoCard.getBoundingClientRect();
        pane.style.top = (infoRect.top - rowRect.top) + 'px';
        pane.style.left = (infoRect.left - rowRect.left) + 'px';
        pane.style.width = infoRect.width + 'px';
        pane.style.height = infoRect.height + 'px';

        return pane;
    }
    function tnZoomShow(el) {
        var pane = tnZoomPositionPane(el);
        if (pane) pane.style.display = 'block';
    }
    function tnZoomHide(el) {
        var row = el.closest('#tnPdRow');
        var pane = row ? row.querySelector('.tn-zoom-pane') : null;
        if (pane) pane.style.display = 'none';
    }
    function tnZoomMove(event, el) {
        var pane = tnZoomPositionPane(el);
        if (!pane) return;
        var rect = el.getBoundingClientRect();
        var x = ((event.clientX - rect.left) / rect.width) * 100;
        var y = ((event.clientY - rect.top) / rect.height) * 100;
        pane.style.backgroundPosition = x + '% ' + y + '%';
    }
</script>
</div>
