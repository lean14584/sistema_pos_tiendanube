<?php

namespace App\Livewire\Tienda\Account;

use App\Support\TiendaCart;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tienda')]
class Login extends Component
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public string $email = '';

    public string $password = '';

    public string $error = '';

    public function submit(): void
    {
        $this->error = '';

        if (RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($this->throttleKey());
            $this->error = "Demasiados intentos. Probá de nuevo en {$seconds} segundos.";

            return;
        }

        $ok = Auth::guard('customer')->attempt([
            'email' => $this->email,
            'password' => $this->password,
        ]);

        if (! $ok) {
            RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);
            $this->error = 'Email o contraseña incorrectos.';

            return;
        }

        RateLimiter::clear($this->throttleKey());
        session()->regenerate();

        TiendaCart::mergeSessionIntoAccount(Auth::guard('customer')->id());

        $this->redirect(route('tienda.catalog'), navigate: true);
    }

    /** Combina email + IP: un ataque contra un email no bloquea a otros clientes desde otra IP, y viceversa. */
    private function throttleKey(): string
    {
        return Str::lower($this->email).'|'.request()->ip();
    }

    public function render()
    {
        return view('livewire.tienda.account.login');
    }
}
