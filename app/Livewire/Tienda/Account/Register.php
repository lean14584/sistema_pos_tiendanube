<?php

namespace App\Livewire\Tienda\Account;

use App\Models\Customer;
use App\Support\TiendaCart;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tienda')]
class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function submit(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:customers,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', PasswordRule::min(8)->mixedCase()->numbers(), 'confirmed'],
        ]);

        $customer = Customer::create([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone ?: null,
            'password' => $this->password,
        ]);

        Auth::guard('customer')->login($customer);
        session()->regenerate();

        // El carrito de invitado (si venía agregando productos antes de
        // registrarse) pasa a ser el carrito de la cuenta recién creada.
        TiendaCart::mergeSessionIntoAccount($customer->id);

        $this->redirect(route('tienda.catalog'), navigate: true);
    }

    public function render()
    {
        return view('livewire.tienda.account.register');
    }
}
