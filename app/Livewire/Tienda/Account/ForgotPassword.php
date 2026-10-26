<?php

namespace App\Livewire\Tienda\Account;

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tienda')]
class ForgotPassword extends Component
{
    private const MAX_ATTEMPTS = 3;

    private const DECAY_SECONDS = 300;

    public string $email = '';

    public bool $sent = false;

    public string $error = '';

    public function submit(): void
    {
        $this->error = '';

        $this->validate(['email' => ['required', 'email']]);

        $key = 'tienda-forgot-password|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $this->error = 'Demasiados pedidos. Probá de nuevo en unos minutos.';

            return;
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);

        Password::broker('customers')->sendResetLink(['email' => $this->email]);

        // Mismo mensaje exista o no ese email: no hay forma de que alguien
        // de afuera use este formulario para averiguar qué cuentas existen.
        $this->sent = true;
    }

    public function render()
    {
        return view('livewire.tienda.account.forgot-password');
    }
}
