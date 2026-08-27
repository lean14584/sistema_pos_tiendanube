<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class Login extends Component
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public string $username = '';

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

        $ok = Auth::attempt([
            'username' => $this->username,
            'password' => $this->password,
            'active' => true,
        ]);

        if (! $ok) {
            RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);
            $this->error = 'Usuario o contraseña incorrectos.';

            return;
        }

        RateLimiter::clear($this->throttleKey());
        session()->regenerate();

        $this->redirect(route('dashboard'), navigate: true);
    }

    /** Combina usuario + IP: un ataque contra "admin" no bloquea a otros usuarios legítimos desde otra IP, y viceversa. */
    private function throttleKey(): string
    {
        return Str::lower($this->username).'|'.request()->ip();
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
