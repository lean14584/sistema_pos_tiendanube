<?php

namespace App\Livewire\Users;

use App\Enums\Role;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public string $name = '';

    public string $username = '';

    public string $email = '';

    public string $password = '';

    public string $role = 'vendedor';

    public string $sucursal_id = '';

    public bool $active = true;

    public function mount(): void
    {
        // Un encargado da de alta usuarios SOLO en su propia sucursal —
        // precargada acá, y de nuevo forzada en save() por si se manipula el
        // valor del campo desde el cliente.
        if (Auth::user()->esEncargado()) {
            $this->sucursal_id = (string) Auth::user()->sucursal_id;
        }
    }

    /** Un encargado no puede dar de alta a otro Admin ni a otro Encargado. */
    private function puedeAsignarRol(Role $role): bool
    {
        return ! Auth::user()->esEncargado() || in_array($role, [Role::Vendedor, Role::Cajero], true);
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::enum(Role::class)],
            // Un admin es global (no pertenece a una sucursal); el resto sí
            // necesita una, pero solo si el cliente usa multisucursal — con
            // el flag apagado no hay selector que llenar (ver save() abajo).
            'sucursal_id' => [Rule::requiredIf($this->role !== Role::Admin->value && config('features.multisucursal')), 'nullable', 'exists:sucursales,id'],
            'active' => ['boolean'],
        ]);

        if (! $this->puedeAsignarRol(Role::from($data['role']))) {
            $this->addError('role', 'No podés asignar ese rol.');

            return;
        }

        // Nunca guardar '' en una columna unique/nullable: dos usuarios sin
        // email chocarían entre sí en el índice único.
        $data['email'] = $data['email'] !== '' ? $data['email'] : null;

        $data['sucursal_id'] = $data['role'] === Role::Admin->value ? null : $data['sucursal_id'];

        // Instalación de una sola sucursal: no hay selector, se asigna la
        // única sucursal existente sin preguntarle nada al usuario.
        if (! config('features.multisucursal') && $data['role'] !== Role::Admin->value) {
            $data['sucursal_id'] = Sucursal::orderBy('id')->value('id');
        }

        if (Auth::user()->esEncargado()) {
            $data['sucursal_id'] = (string) Auth::user()->sucursal_id;
        }

        User::create($data);

        session()->flash('status', 'Usuario creado.');
        $this->redirect(route('users.index'), navigate: true);
    }

    public function render()
    {
        $esEncargado = Auth::user()->esEncargado();

        return view('livewire.users.create', [
            'roles' => $esEncargado ? [Role::Vendedor, Role::Cajero] : Role::cases(),
            'sucursales' => $esEncargado
                ? Sucursal::where('id', Auth::user()->sucursal_id)->get()
                : Sucursal::where('active', true)->orderBy('name')->get(),
        ]);
    }
}
