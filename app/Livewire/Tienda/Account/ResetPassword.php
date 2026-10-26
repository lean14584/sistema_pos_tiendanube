<?php

namespace App\Livewire\Tienda\Account;

use App\Models\Customer;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.tienda')]
class ResetPassword extends Component
{
    public string $token = '';

    #[Url]
    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $error = '';

    public function mount(string $token): void
    {
        $this->token = $token;
    }

    public function submit(): void
    {
        $this->error = '';

        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', PasswordRule::min(8)->mixedCase()->numbers(), 'confirmed'],
        ]);

        $status = Password::broker('customers')->reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function (Customer $customer, string $password) {
                $customer->forceFill(['password' => $password])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            $this->error = 'El link no es válido o ya venció. Pedí uno nuevo desde "Olvidé mi contraseña".';

            return;
        }

        session()->flash('status', 'Contraseña actualizada. Ya podés iniciar sesión.');
        $this->redirect(route('tienda.account.login'), navigate: true);
    }

    public function render()
    {
        return view('livewire.tienda.account.reset-password');
    }
}
