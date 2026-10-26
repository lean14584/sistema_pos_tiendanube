<div class="container py-5">
    <div class="tn-card mx-auto p-4 p-md-5" style="max-width: 420px;">
        <h1 class="h3 mb-4">Elegir nueva contraseña</h1>

        <form wire:submit="submit">
            <div class="mb-3">
                <label class="form-label small">Email</label>
                <input type="email" wire:model="email" class="form-control @error('email') is-invalid @enderror">
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label class="form-label small">Contraseña nueva</label>
                <input type="password" wire:model="password" autofocus class="form-control @error('password') is-invalid @enderror">
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="mb-4">
                <label class="form-label small">Repetir contraseña</label>
                <input type="password" wire:model="password_confirmation" class="form-control">
            </div>

            @if ($error)
                <div class="alert-tn-danger p-3 small mb-3">{{ $error }}</div>
            @endif

            <button type="submit" wire:loading.attr="disabled" wire:target="submit" class="btn btn-tn-primary w-100">
                <span wire:loading.remove wire:target="submit">Guardar</span>
                <span wire:loading wire:target="submit">Guardando...</span>
            </button>
        </form>
    </div>
</div>
