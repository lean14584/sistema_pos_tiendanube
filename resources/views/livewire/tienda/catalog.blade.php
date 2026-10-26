<div class="container py-4 py-md-5">
    <div class="row">

        {{-- SIDEBAR DE FILTROS --}}
        <aside class="col-md-3 col-lg-2 d-none d-md-block mb-4">
            <div class="pe-3">
                @if($query !== '' || $categoryId || $sort !== 'recientes')
                    <button type="button" wire:click="limpiarFiltros" class="btn btn-sm btn-tn-outline mb-3" style="padding: .3rem .9rem; font-size: .8rem;">
                        Limpiar filtros
                    </button>
                @endif

                <div class="mb-1" style="font-weight: 600; font-size: .85rem; color: var(--muted);">Categorías</div>
                <button type="button" wire:click="$set('categoryId', null)" class="d-block btn btn-link text-start p-0 py-1 text-decoration-none" style="color: {{ ! $categoryId ? 'var(--market)' : 'var(--ink)' }}; font-weight: {{ ! $categoryId ? '600' : '400' }};">
                    Todo
                </button>
                @foreach($categories as $cat)
                    <button type="button" wire:click="$set('categoryId', {{ $cat->id }})" class="d-block btn btn-link text-start p-0 py-1 text-decoration-none" style="color: {{ (int) $categoryId === (int) $cat->id ? 'var(--market)' : 'var(--ink)' }}; font-weight: {{ (int) $categoryId === (int) $cat->id ? '600' : '400' }};">
                        {{ $cat->name }}
                    </button>
                @endforeach
            </div>
        </aside>

        {{-- CONTENIDO PRINCIPAL --}}
        <main class="col-12 col-md-9 col-lg-10">

            <div class="d-flex flex-column flex-md-row gap-2 align-items-md-center justify-content-between pb-3 mb-4" style="border-bottom: 1px solid var(--rule);">
                <div class="input-group" style="max-width: 360px;">
                    <input type="text" wire:model.live.debounce.400ms="query" class="form-control" placeholder="Buscar en el catálogo...">
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

            <div class="row g-4 g-md-5">
                @forelse($products as $item)
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="{{ route('tienda.product', $item) }}" class="text-decoration-none d-block">
                            <div class="d-flex align-items-center justify-content-center bg-white" style="aspect-ratio: 1; border-bottom: 1px solid var(--rule); padding: 1.5rem;">
                                @if($item->imageUrl())
                                    <img src="{{ $item->imageUrl() }}" alt="{{ $item->name }}" loading="lazy" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                                @else
                                    <i class="fas fa-basket-shopping" style="font-size: 2.2rem; color: var(--rule);"></i>
                                @endif
                            </div>
                            <div class="pt-2">
                                <div style="font-size: .72rem; color: var(--muted);">{{ $item->category?->name ?? 'general' }}</div>
                                <div style="color: var(--ink); font-weight: 600; font-size: .92rem; margin: .15rem 0 .35rem;" class="text-truncate">
                                    {{ $item->name }}
                                </div>
                                <div class="tn-price" style="font-size: 1.3rem;">$ {{ number_format((float) $item->price, 2, ',', '.') }}</div>
                            </div>
                        </a>
                    </div>
                @empty
                    <div class="col-12 text-center py-5">
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
