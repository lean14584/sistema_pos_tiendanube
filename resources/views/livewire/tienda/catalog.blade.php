<div class="container py-4 py-md-5">

    @if($query === '' && ! $categoryId)
        <div class="tn-hero mb-3">
            <div class="tn-hero-title">Todo lo que buscás,<br>a un clic de distancia</div>
            <p class="tn-hero-sub">Stock real, al mismo precio que en el local. Elegí, pagá online y listo.</p>
            <div class="mt-3">
                <a href="#tn-grid" class="tn-chip"><i class="fas fa-arrow-down me-1"></i> Ver catálogo</a>
                @foreach($categories->take(4) as $cat)
                    <button type="button" wire:click="$set('categoryId', {{ $cat->id }})" class="tn-chip border-0">{{ $cat->name }}</button>
                @endforeach
            </div>
        </div>

        <div class="tn-trustbar mb-5">
            <div class="tn-trust-item">
                <i class="fas fa-boxes-stacked"></i>
                <span>Stock real, al segundo</span>
            </div>
            <div class="tn-trust-item">
                <i class="fas fa-lock"></i>
                <span>Pago seguro con Mercado Pago</span>
            </div>
            <div class="tn-trust-item">
                <i class="fas fa-shop"></i>
                <span>Mismo precio que en el local</span>
            </div>
        </div>
    @endif

    <div class="row">

        {{-- SIDEBAR DE FILTROS --}}
        <aside class="col-md-3 col-lg-2 d-none d-md-block mb-4">
            <div class="tn-card p-3">
                @if($query !== '' || $categoryId || $sort !== 'recientes')
                    <button type="button" wire:click="limpiarFiltros" class="btn btn-sm btn-tn-outline mb-3 w-100" style="padding: .4rem .9rem; font-size: .8rem;">
                        Limpiar filtros
                    </button>
                @endif

                <div class="mb-2" style="font-weight: 800; font-size: .82rem; color: var(--tn-muted); text-transform: none;">Categorías</div>
                <button type="button" wire:click="$set('categoryId', null)" class="d-block btn btn-link text-start p-0 py-2 text-decoration-none" style="color: {{ ! $categoryId ? 'var(--tn-700)' : 'var(--tn-ink)' }}; font-weight: {{ ! $categoryId ? '700' : '500' }};">
                    <i class="fas fa-grip me-2" style="width:16px;"></i>Todo
                </button>
                @foreach($categories as $cat)
                    <button type="button" wire:click="$set('categoryId', {{ $cat->id }})" class="d-block btn btn-link text-start p-0 py-2 text-decoration-none" style="color: {{ (int) $categoryId === (int) $cat->id ? 'var(--tn-700)' : 'var(--tn-ink)' }}; font-weight: {{ (int) $categoryId === (int) $cat->id ? '700' : '500' }};">
                        {{ $cat->name }}
                    </button>
                @endforeach
            </div>
        </aside>

        {{-- CONTENIDO PRINCIPAL --}}
        <main class="col-12 col-md-9 col-lg-10" id="tn-grid">

            <div class="tn-card d-flex flex-column flex-md-row gap-2 align-items-md-center justify-content-between p-3 mb-4">
                <div class="position-relative" style="max-width: 420px; width: 100%;">
                    <i class="fas fa-magnifying-glass position-absolute" style="left: 14px; top: 50%; transform: translateY(-50%); color: var(--tn-muted); font-size: .85rem;"></i>
                    <input type="text" wire:model.live.debounce.400ms="query" class="form-control" style="padding-left: 2.3rem;" placeholder="Buscar en el catálogo...">
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small">Ordenar:</span>
                    <select wire:model.live="sort" class="form-select form-select-sm w-auto">
                        <option value="recientes">Recientes</option>
                        <option value="precio_asc">Menor precio</option>
                        <option value="precio_desc">Mayor precio</option>
                    </select>
                </div>
            </div>

            <div class="row g-3 g-md-4">
                @forelse($products as $item)
                    @php $stock = $item->stockEnSucursal($sucursalId); @endphp
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="{{ route('tienda.product', $item) }}" class="text-decoration-none d-block tn-card h-100">
                            <div class="tn-card-media {{ $stock <= 0 ? 'tn-card-media-out' : '' }}" style="aspect-ratio: 1; background: var(--tn-paper); padding: 1.5rem;">
                                @if($item->imageUrl())
                                    <img src="{{ $item->imageUrl() }}" alt="{{ $item->name }}" loading="lazy" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                                @else
                                    <i class="fas fa-basket-shopping" style="font-size: 2.4rem; color: var(--tn-line);"></i>
                                @endif

                                @if($stock <= 0)
                                    <span class="tn-ribbon tn-ribbon-off">Sin stock</span>
                                @elseif($stock <= 10)
                                    <span class="tn-ribbon tn-ribbon-urgent">¡Últimas {{ $stock }}!</span>
                                @endif
                            </div>
                            <div class="p-3">
                                <div style="font-size: .72rem; color: var(--tn-muted); font-weight: 600;">{{ $item->category?->name ?? 'General' }}</div>
                                <div style="color: var(--tn-ink); font-weight: 700; font-size: .92rem; margin: .2rem 0 .5rem; line-height: 1.3;" class="text-truncate">
                                    {{ $item->name }}
                                </div>
                                <div class="d-flex align-items-baseline justify-content-between">
                                    <div class="tn-price" style="font-size: 1.35rem;">$ {{ number_format((float) $item->price, 2, ',', '.') }}</div>
                                    @if($stock > 0 && $stock <= 10)
                                        <span style="font-size: .72rem; font-weight: 700; color: #B3261E;">Quedan {{ $stock }}</span>
                                    @endif
                                </div>
                            </div>
                        </a>
                    </div>
                @empty
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-magnifying-glass mb-3" style="font-size: 2rem; color: var(--tn-line);"></i>
                        <p class="fw-bold mb-1">No encontramos nada con esa búsqueda</p>
                        <p class="text-muted small">Probá con otra palabra o mirá todo el catálogo.</p>
                    </div>
                @endforelse
            </div>

            <div class="mt-5">
                {{ $products->links() }}
            </div>

        </main>
    </div>
</div>
