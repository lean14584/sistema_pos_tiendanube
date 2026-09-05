<?php

namespace App\Livewire\Users;

use App\Enums\Role;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Edit extends Component
{
    public User $user;

    public string $name = '';

    public string $username = '';

    public string $password = '';

    public string $current_password = '';

    public string $role = 'vendedor';

    public string $sucursal_id = '';

    public bool $active = true;

    public function mount(User $user): void
    {
        $this->user = $user;
        $this->name = $user->name;
        $this->username = $user->username;
        $this->role = $user->role->value;
        $this->sucursal_id = $user->sucursal_id ? (string) $user->sucursal_id : '';
        $this->active = $user->active;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($this->user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', Rule::enum(Role::class)],
            'sucursal_id' => [Rule::requiredIf($this->role !== Role::Admin->value), 'nullable', 'exists:sucursales,id'],
            'active' => ['boolean'],
        ]);

        $data['sucursal_id'] = $data['role'] === Role::Admin->value ? null : $data['sucursal_id'];

        if (empty($data['password'])) {
            unset($data['password']);
        }

        // Una sesión de admin secuestrada podría, si no fuera por esto,
        // cambiarse la contraseña sin conocer la actual y expulsar al dueño
        // legítimo de su propia cuenta. Solo aplica al cambiar la propia
        // contraseña (no bloquea a un admin editando la de otro usuario).
        if ($this->user->id === Auth::id() && isset($data['password'])) {
            if (! Hash::check($this->current_password, $this->user->password)) {
                $this->addError('current_password', 'La contraseña actual no es correcta.');

                return;
            }
        }

        // Mismo resguardo que ya tiene Index::delete(): sin esto, editar al
        // único admin activo (sacarle el rol o desactivarlo) deja el sistema
        // sin nadie que pueda entrar a Usuarios/Configuración/Auditoría, y no
        // hay forma de revertirlo desde la interfaz.
        $dejaDeSerAdminActivo = $this->user->role === Role::Admin && $this->user->active
            && (Role::from($data['role']) !== Role::Admin || ! ($data['active'] ?? false));

        if ($dejaDeSerAdminActivo) {
            $activeAdmins = User::where('role', Role::Admin)->where('active', true)->count();

            if ($activeAdmins <= 1) {
                $this->addError('role', 'No se puede sacar el rol de administrador ni desactivar al último administrador activo del sistema.');

                return;
            }
        }

        $this->user->update($data);

        session()->flash('status', 'Usuario actualizado.');
        $this->redirect(route('users.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.users.edit', [
            'roles' => Role::cases(),
            // Incluye la sucursal actual del usuario aunque esté inactiva, para
            // no romper el <select> si se desactivó después de asignarla.
            'sucursales' => Sucursal::where('active', true)->orWhere('id', $this->user->sucursal_id)->orderBy('name')->get(),
            'editingSelf' => $this->user->id === Auth::id(),
        ]);
    }
}
