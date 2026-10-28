<div class="container py-4 py-md-5">
    <h1 class="h3 mb-4">Finalizar compra</h1>

    @if($lines->isEmpty())
        <div class="tn-card text-center py-5">
            <p class="fw-bold mb-1">Tu carrito está vacío</p>
            <a href="{{ route('tienda.catalog') }}" class="btn btn-tn-primary mt-2">Ver catálogo</a>
        </div>
    @else
        <div class="row g-4 g-md-5">
            <div class="col-12 col-md-7">
                <div class="tn-card p-3 p-md-4">
                    <div style="font-weight: 800; font-size: .85rem; color: var(--tn-muted);" class="mb-3">Tus datos</div>

                    @error('cart')
                        <div class="alert-tn-danger p-3 mb-3">{{ $message }}</div>
                    @enderror

                    <form wire:submit="confirmar">
                        <div class="mb-3">
                            <label class="form-label small">Nombre y apellido</label>
                            <input type="text" wire:model="customer_name" class="form-control @error('customer_name') is-invalid @enderror">
                            @error('customer_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Email</label>
                            <input type="email" wire:model="customer_email" class="form-control @error('customer_email') is-invalid @enderror">
                            @error('customer_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-4">
                            <label class="form-label small">Teléfono (opcional)</label>
                            <input type="text" wire:model="customer_phone" class="form-control @error('customer_phone') is-invalid @enderror">
                            @error('customer_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        @if($mpConfigured)
                            <div class="mb-4">
                                <label class="form-label small d-block mb-2">¿Cómo querés pagar?</label>
                                <label class="tn-pay-option d-flex align-items-center gap-2 mb-2">
                                    <input type="radio" wire:model="payment_choice" value="mercadopago">
                                    <span>
                                        <span class="d-block fw-bold">Pagar ahora con Mercado Pago</span>
                                        <span class="d-block small text-muted">Te llevamos a pagar online, al toque.</span>
                                    </span>
                                </label>
                                <label class="tn-pay-option d-flex align-items-center gap-2">
                                    <input type="radio" wire:model="payment_choice" value="retiro">
                                    <span>
                                        <span class="d-block fw-bold">Pagar al retirar en el local</span>
                                        <span class="d-block small text-muted">Reservamos tu pedido y pagás en efectivo o tarjeta cuando pasás a buscarlo.</span>
                                    </span>
                                </label>
                                @error('payment_choice') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                        @else
                            <div class="alert-tn-info p-3 small mb-3">
                                <i class="fas fa-circle-info me-1"></i>
                                Coordinamos el pago al retirar y te contactamos.
                            </div>
                        @endif

                        <button type="submit" wire:loading.attr="disabled" wire:target="confirmar" class="btn btn-tn-primary w-100">
                            <span wire:loading.remove wire:target="confirmar">
                                {{ $mpConfigured && $payment_choice === 'mercadopago' ? 'Ir a pagar' : 'Confirmar pedido' }}
                            </span>
                            <span wire:loading wire:target="confirmar">Confirmando...</span>
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-12 col-md-5">
                <div class="tn-card p-3 p-md-4">
                    <div style="font-weight: 800; font-size: .85rem; color: var(--tn-muted);" class="mb-3">Tu pedido</div>
                    @foreach($lines as $line)
                        <div class="d-flex justify-content-between small py-2" style="border-bottom: 1px solid var(--tn-line);">
                            <span>{{ $line['quantity'] }}× {{ $line['product']->name }}</span>
                            <span class="fw-bold">$ {{ number_format($line['subtotal'], 2, ',', '.') }}</span>
                        </div>
                    @endforeach
                    <div class="d-flex justify-content-between pt-3">
                        <span class="fw-bold">Total</span>
                        <span class="tn-price" style="font-size: 1.5rem;">$ {{ number_format($total, 2, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
