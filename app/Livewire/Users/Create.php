<?php

namespace App\Livewire\Users;

use App\Enums\Role;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public string $name = '';

    public string $username = '';

    public string $password = '';

    public string $role = 'vendedor';

    public string $sucursal_id = '';

    public bool $active = true;

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::enum(Role::class)],
            // Un admin es global (no pertenece a una sucursal); cajero/vendedor sí necesitan una.
            'sucursal_id' => [Rule::requiredIf($this->role !== Role::Admin->value), 'nullable', 'exists:sucursales,id'],
            'active' => ['boolean'],
        ]);

        $data['sucursal_id'] = $data['role'] === Role::Admin->value ? null : $data['sucursal_id'];

        User::create($data);

        session()->flash('status', 'Usuario creado.');
        $this->redirect(route('users.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.users.create', [
            'roles' => Role::cases(),
            'sucursales' => Sucursal::where('active', true)->orderBy('name')->get(),
        ]);
    }
}
