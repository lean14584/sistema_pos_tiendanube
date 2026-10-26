<div class="container py-5">
    <div class="tn-card mx-auto p-4 p-md-5" style="max-width: 440px;">
        <h1 class="h3 mb-4">Crear cuenta</h1>

        <form wire:submit="submit">
            <div class="mb-3">
                <label class="form-label small">Nombre y apellido</label>
                <input type="text" wire:model="name" autofocus class="form-control @error('name') is-invalid @enderror">
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label class="form-label small">Email</label>
                <input type="email" wire:model="email" class="form-control @error('email') is-invalid @enderror">
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label class="form-label small">Teléfono (opcional)</label>
                <input type="text" wire:model="phone" class="form-control @error('phone') is-invalid @enderror">
                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label class="form-label small">Contraseña</label>
                <input type="password" wire:model="password" class="form-control @error('password') is-invalid @enderror">
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">Mínimo 8 caracteres, con mayúscula, minúscula y número.</div>
            </div>
            <div class="mb-4">
                <label class="form-label small">Repetir contraseña</label>
                <input type="password" wire:model="password_confirmation" class="form-control">
            </div>

            <button type="submit" wire:loading.attr="disabled" wire:target="submit" class="btn btn-tn-primary w-100">
                <span wire:loading.remove wire:target="submit">Crear cuenta</span>
                <span wire:loading wire:target="submit">Creando...</span>
            </button>
        </form>

        <p class="text-center small mt-3 mb-0">
            ¿Ya tenés cuenta? <a href="{{ route('tienda.account.login') }}">Ingresar</a>
        </p>
    </div>
</div>
