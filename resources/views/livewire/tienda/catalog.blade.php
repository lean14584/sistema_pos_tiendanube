<div class="container-fluid px-md-4 px-lg-5 py-3 py-md-4">
    <div class="row">

        {{-- SIDEBAR DE FILTROS --}}
        <aside class="col-md-3 col-lg-2 d-none d-md-block mb-4">
            <div class="tn-filter-sidebar p-2">
                <h4 class="tn-filter-title">Filtros</h4>

                @if($query !== '' || $categoryId || $sort !== 'recientes')
                    <div class="mb-3">
                        <button type="button" wire:click="limpiarFiltros" class="btn btn-sm btn-secondary rounded-pill px-3 py-1" style="font-size:.75rem;">
                            Limpiar filtros <i class="fas fa-times ms-1"></i>
                        </button>
                    </div>
                @endif

                <div class="tn-filter-group-title">Categorías</div>
                <button type="button" wire:click="$set('categoryId', null)" class="tn-filter-link btn btn-link p-0 text-start {{ ! $categoryId ? 'active' : '' }}">
                    Todas las categorías
                </button>
                @foreach($categories as $cat)
                    <button type="button" wire:click="$set('categoryId', {{ $cat->id }})" class="tn-filter-link btn btn-link p-0 text-start d-block {{ (int) $categoryId === (int) $cat->id ? 'active' : '' }}">
                        {{ $cat->name }}
                    </button>
                @endforeach
            </div>
        </aside>

        {{-- CONTENIDO PRINCIPAL --}}
        <main class="col-12 col-md-9 col-lg-10">

            <div class="bg-white p-3 rounded-4 shadow-sm mb-3 mb-md-4">
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-md-7 col-lg-8">
                        <div class="input-group">
                            <input type="text" wire:model.live.debounce.400ms="query" class="form-control border-secondary-subtle" placeholder="Buscar productos...">
                            <span class="input-group-text bg-dark text-white"><i class="fas fa-search"></i></span>
                        </div>
                    </div>
                    <div class="col-12 col-md-5 col-lg-4 d-flex align-items-center justify-content-md-end gap-2">
                        <span class="text-muted small text-nowrap">Ordenar por:</span>
                        <select wire:model.live="sort" class="form-select form-select-sm w-auto border-secondary-subtle">
                            <option value="recientes">Más recientes</option>
                            <option value="precio_asc">Menor precio</option>
                            <option value="precio_desc">Mayor precio</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="mb-3 text-muted small px-1">
                {{ $products->total() }} {{ Str::plural('producto', $products->total()) }}
                @if($query !== '') para "<strong>{{ $query }}</strong>"@endif
            </div>

            <div class="row g-2 g-md-4">
                @forelse($products as $item)
                    <div class="col-6 col-sm-6 col-md-4 col-lg-3">
                        <a href="{{ route('tienda.product', $item) }}" class="text-decoration-none">
                            <div class="tn-product-card d-flex flex-column justify-content-between">
                                <div class="tn-img-container">
                                    @if($item->imageUrl())
                                        <img src="{{ $item->imageUrl() }}" alt="{{ $item->name }}" loading="lazy">
                                    @else
                                        <i class="fas fa-box-open text-muted fs-1"></i>
                                    @endif
                                </div>
                                <div class="p-2 p-md-3 d-flex flex-column flex-grow-1 justify-content-between">
                                    <div>
                                        <span class="text-muted d-none d-md-block small text-uppercase fw-bold" style="font-size:.65rem; letter-spacing:.5px;">
                                            {{ $item->category?->name ?? 'General' }}
                                        </span>
                                        <h6 class="fw-bold my-1 text-truncate" title="{{ $item->name }}" style="color:#333; font-size:.9rem;">
                                            {{ $item->name }}
                                        </h6>
                                    </div>
                                    <div class="mt-1 mt-md-2">
                                        <span class="tn-price">$ {{ number_format((float) $item->price, 2, ',', '.') }}</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                @empty
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-box-open text-muted fs-1 mb-3"></i>
                        <h5 class="text-secondary fw-bold">No encontramos resultados</h5>
                        <p class="text-muted small">Probá usando términos más generales.</p>
                    </div>
                @endforelse
            </div>

            <div class="mt-4">
                {{ $products->links() }}
            </div>

        </main>
    </div>
</div>
