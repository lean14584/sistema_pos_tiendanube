<div class="container py-5">
    <div class="tn-card mx-auto p-4 p-md-5" style="max-width: 420px;">
        <h1 class="h3 mb-4">Ingresar</h1>

        @if (session('status'))
            <div class="alert-tn-info p-3 small mb-3">{{ session('status') }}</div>
        @endif

        <form wire:submit="submit">
            <div class="mb-3">
                <label class="form-label small">Email</label>
                <input type="email" wire:model="email" autofocus class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label small">Contraseña</label>
                <input type="password" wire:model="password" class="form-control">
            </div>

            @if ($error)
                <div class="alert-tn-danger p-3 small mb-3">{{ $error }}</div>
            @endif

            <button type="submit" wire:loading.attr="disabled" wire:target="submit" class="btn btn-tn-primary w-100">
                <span wire:loading.remove wire:target="submit">Ingresar</span>
                <span wire:loading wire:target="submit">Ingresando...</span>
            </button>
        </form>

        <div class="d-flex justify-content-between mt-3 small">
            <a href="{{ route('tienda.account.forgot-password') }}">Olvidé mi contraseña</a>
            <a href="{{ route('tienda.account.register') }}">Crear cuenta</a>
        </div>
    </div>
</div>
