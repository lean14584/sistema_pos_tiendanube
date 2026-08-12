<div
    class="min-h-screen flex flex-col items-center justify-center p-6"
    x-data="{ timer: null }"
    x-init="$refs.code.focus()"
    x-on:scanned.window="$nextTick(() => $refs.code.focus())"
    x-on:result-shown.window="clearTimeout(timer); timer = setTimeout(() => $wire.resetView(), 10000)"
    x-on:click="$refs.code.focus()"
>
    <div class="w-full max-w-2xl">
        <div class="text-center mb-8">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $companyName }}" class="h-20 sm:h-24 mx-auto mb-4 object-contain drop-shadow">
            @endif
            <h1 class="text-3xl sm:text-4xl font-bold text-white drop-shadow">Consultá tu precio</h1>
            <p class="text-white/80 mt-2 text-lg">Pasá el producto por el lector</p>
        </div>

        {{-- Input del lector: recibe el foco siempre; el scanner tipea y manda Enter --}}
        <form wire:submit="search" class="mb-8">
            <input
                x-ref="code"
                type="text"
                wire:model="code"
                autocomplete="off"
                placeholder="Escaneá o escribí el código y Enter"
                class="w-full text-center text-xl rounded-2xl border-0 px-6 py-4 shadow-xl focus:outline-none focus:ring-4 focus:ring-white/50 text-gray-900"
            >
        </form>

        {{-- Resultado --}}
        <div class="min-h-[16rem] flex items-center justify-center">
            @if ($product)
                <div class="w-full bg-white rounded-3xl shadow-2xl p-10 text-center">
                    @if (! empty($product['image']))
                        <img src="{{ $product['image'] }}" alt="{{ $product['name'] }}" class="w-40 h-40 sm:w-48 sm:h-48 mx-auto mb-6 rounded-2xl object-cover shadow-lg">
                    @endif
                    <p class="text-2xl sm:text-3xl font-semibold text-gray-800 mb-2">{{ $product['name'] }}</p>
                    @if ($product['sku'])
                        <p class="text-sm text-gray-400 mb-6">Código: {{ $product['sku'] }}</p>
                    @endif
                    <p class="text-6xl sm:text-7xl font-extrabold text-indigo-600 tracking-tight">
                        ${{ number_format($product['price'], 2) }}
                    </p>
                    <p class="mt-6 text-sm {{ $product['stock'] > 0 ? 'text-emerald-600' : 'text-red-500' }}">
                        {{ $product['stock'] > 0 ? 'Disponible' : 'Sin stock' }}
                    </p>
                </div>
            @elseif ($notFound)
                <div class="w-full bg-white/95 rounded-3xl shadow-2xl p-10 text-center">
                    <p class="text-5xl mb-4">🔍</p>
                    <p class="text-2xl font-semibold text-gray-700">Producto no encontrado</p>
                    <p class="text-gray-500 mt-2">Probá de nuevo o consultá en caja.</p>
                </div>
            @else
                <div class="text-center text-white/70">
                    <p class="text-7xl mb-4">🏷️</p>
                    <p class="text-lg">Esperando lectura...</p>
                </div>
            @endif
        </div>
    </div>
</div>
