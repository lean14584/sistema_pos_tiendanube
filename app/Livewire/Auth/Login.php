<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class Login extends Component
{
    public string $username = '';

    public string $password = '';

    public string $error = '';

    public function submit(): void
    {
        $this->error = '';

        $ok = Auth::attempt([
            'username' => $this->username,
            'password' => $this->password,
            'active' => true,
        ]);

        if (! $ok) {
            $this->error = 'Usuario o contraseña incorrectos.';

            return;
        }

        session()->regenerate();

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
