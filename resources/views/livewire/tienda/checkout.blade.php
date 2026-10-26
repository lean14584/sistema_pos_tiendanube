<div class="container py-4 py-md-5">
    <h1 class="h3 mb-4">Finalizar compra</h1>

    @if($lines->isEmpty())
        <div class="text-center py-5" style="border: 1px solid var(--rule); border-radius: 6px;">
            <p class="fw-bold mb-1">Tu carrito está vacío</p>
            <a href="{{ route('tienda.catalog') }}" class="btn btn-tn-primary mt-2">Ver catálogo</a>
        </div>
    @else
        <div class="row g-4 g-md-5">
            <div class="col-12 col-md-7">
                <div style="font-weight: 600; font-size: .85rem; color: var(--muted);" class="mb-3">Tus datos</div>

                @error('cart')
                    <div class="alert-tn-danger p-3 mb-3">{{ $message }}</div>
                @enderror

                <form wire:submit="confirmar">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nombre y apellido</label>
                        <input type="text" wire:model="customer_name" class="form-control @error('customer_name') is-invalid @enderror">
                        @error('customer_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Email</label>
                        <input type="email" wire:model="customer_email" class="form-control @error('customer_email') is-invalid @enderror">
                        @error('customer_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold">Teléfono (opcional)</label>
                        <input type="text" wire:model="customer_phone" class="form-control @error('customer_phone') is-invalid @enderror">
                        @error('customer_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="alert-tn-info p-3 small mb-3">
                        Al confirmar te llevamos a pagar con Mercado Pago. Si todavía no está disponible el pago online, coordinamos el pago al retirar y te contactamos.
                    </div>

                    <button type="submit" wire:loading.attr="disabled" wire:target="confirmar" class="btn btn-tn-primary w-100">
                        <span wire:loading.remove wire:target="confirmar">Ir a pagar</span>
                        <span wire:loading wire:target="confirmar">Confirmando...</span>
                    </button>
                </form>
            </div>

            <div class="col-12 col-md-5">
                <div style="font-weight: 600; font-size: .85rem; color: var(--muted);" class="mb-3">Tu pedido</div>
                <div style="border-top: 1px solid var(--rule);">
                    @foreach($lines as $line)
                        <div class="d-flex justify-content-between small py-2" style="border-bottom: 1px solid var(--rule);">
                            <span>{{ $line['quantity'] }}× {{ $line['product']->name }}</span>
                            <span class="fw-bold">$ {{ number_format($line['subtotal'], 2, ',', '.') }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="d-flex justify-content-between pt-3">
                    <span class="fw-bold">Total</span>
                    <span class="tn-price" style="font-size: 1.4rem;">$ {{ number_format($total, 2, ',', '.') }}</span>
                </div>
            </div>
        </div>
    @endif
</div>
