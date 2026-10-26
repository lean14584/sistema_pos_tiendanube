<div class="container py-5">
    <div class="tn-card mx-auto p-4 p-md-5" style="max-width: 420px;">
        <h1 class="h3 mb-4">Olvidé mi contraseña</h1>

        @if ($sent)
            <div class="alert-tn-info p-3 small">
                Si ese email tiene una cuenta, te mandamos un link para elegir una contraseña nueva.
            </div>
        @else
            <form wire:submit="submit">
                <div class="mb-3">
                    <label class="form-label small">Email</label>
                    <input type="email" wire:model="email" autofocus class="form-control @error('email') is-invalid @enderror">
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                @if ($error)
                    <div class="alert-tn-danger p-3 small mb-3">{{ $error }}</div>
                @endif

                <button type="submit" wire:loading.attr="disabled" wire:target="submit" class="btn btn-tn-primary w-100">
                    <span wire:loading.remove wire:target="submit">Enviar link</span>
                    <span wire:loading wire:target="submit">Enviando...</span>
                </button>
            </form>
        @endif

        <p class="text-center small mt-3 mb-0">
            <a href="{{ route('tienda.account.login') }}">Volver a ingresar</a>
        </p>
    </div>
</div>
