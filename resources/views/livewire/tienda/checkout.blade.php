<div class="container py-4 py-md-5">
    <h1 class="h3 fw-bold mb-4">Finalizar compra</h1>

    @if($lines->isEmpty())
        <div class="bg-white rounded-4 shadow-sm p-5 text-center">
            <i class="fas fa-box-open text-muted fs-1 mb-3"></i>
            <h5 class="text-secondary fw-bold">Tu carrito está vacío</h5>
            <a href="{{ route('tienda.catalog') }}" class="btn btn-dark rounded-pill px-4 mt-3">Ver catálogo</a>
        </div>
    @else
        <div class="row g-4">
            <div class="col-12 col-md-7">
                <div class="bg-white rounded-4 shadow-sm p-3 p-md-4">
                    <h2 class="h6 fw-bold text-uppercase text-muted mb-3">Tus datos</h2>

                    @error('cart')
                        <div class="alert alert-danger py-2">{{ $message }}</div>
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
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Teléfono (opcional)</label>
                            <input type="text" wire:model="customer_phone" class="form-control @error('customer_phone') is-invalid @enderror">
                            @error('customer_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="alert alert-info small mb-3">
                            <i class="fas fa-info-circle me-1"></i>
                            Al confirmar te llevamos a pagar con Mercado Pago. Si todavía no está disponible el pago online, coordinamos el pago al retirar y te contactamos.
                        </div>

                        <button type="submit" wire:loading.attr="disabled" wire:target="confirmar" class="btn btn-dark rounded-pill px-4 w-100">
                            <span wire:loading.remove wire:target="confirmar"><i class="fab fa-cc-mastercard me-1"></i> Ir a pagar</span>
                            <span wire:loading wire:target="confirmar"><i class="fas fa-spinner fa-spin me-1"></i> Confirmando...</span>
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-12 col-md-5">
                <div class="bg-white rounded-4 shadow-sm p-3 p-md-4">
                    <h2 class="h6 fw-bold text-uppercase text-muted mb-3">Tu pedido</h2>
                    @foreach($lines as $line)
                        <div class="d-flex justify-content-between small py-2 border-bottom">
                            <span>{{ $line['quantity'] }}x {{ $line['product']->name }}</span>
                            <span class="fw-bold">$ {{ number_format($line['subtotal'], 2, ',', '.') }}</span>
                        </div>
                    @endforeach
                    <div class="d-flex justify-content-between pt-3">
                        <span class="fw-bold">Total</span>
                        <span class="tn-price">$ {{ number_format($total, 2, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
