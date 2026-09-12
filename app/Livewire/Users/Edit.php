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

    public string $email = '';

    public string $password = '';

    public string $current_password = '';

    public string $role = 'vendedor';

    public string $sucursal_id = '';

    public bool $active = true;

    public function mount(User $user): void
    {
        $actor = Auth::user();

        // Un encargado solo edita usuarios Cajero/Vendedor de SU sucursal (o
        // a sí mismo, para cambiar su propia contraseña — ver save()).
        if ($actor->esEncargado() && $user->id !== $actor->id) {
            abort_unless(
                $user->sucursal_id === $actor->sucursal_id && in_array($user->role, [Role::Vendedor, Role::Cajero], true),
                403,
                'No podés editar este usuario.'
            );
        }

        $this->user = $user;
        $this->name = $user->name;
        $this->username = $user->username;
        $this->email = $user->email ?? '';
        $this->role = $user->role->value;
        $this->sucursal_id = $user->sucursal_id ? (string) $user->sucursal_id : '';
        $this->active = $user->active;
    }

    /** Un encargado no puede ascender a nadie a Admin/Encargado (excepto no tocarse su propio rol, ver save()). */
    private function puedeAsignarRol(Role $role): bool
    {
        $actor = Auth::user();

        return ! $actor->esEncargado() || $this->user->id === $actor->id || in_array($role, [Role::Vendedor, Role::Cajero], true);
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($this->user->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', Rule::enum(Role::class)],
            // Solo se exige sucursal si el cliente usa multisucursal — con el
            // flag apagado no hay selector que llenar (ver abajo).
            'sucursal_id' => [Rule::requiredIf($this->role !== Role::Admin->value && config('features.multisucursal')), 'nullable', 'exists:sucursales,id'],
            'active' => ['boolean'],
        ]);

        if (! $this->puedeAsignarRol(Role::from($data['role']))) {
            $this->addError('role', 'No podés asignar ese rol.');

            return;
        }

        $data['email'] = $data['email'] !== '' ? $data['email'] : null;

        $data['sucursal_id'] = $data['role'] === Role::Admin->value ? null : $data['sucursal_id'];

        // Instalación de una sola sucursal: no hay selector, se asigna la
        // única sucursal existente sin preguntarle nada al usuario.
        if (! config('features.multisucursal') && $data['role'] !== Role::Admin->value) {
            $data['sucursal_id'] = Sucursal::orderBy('id')->value('id');
        }

        $actor = Auth::user();

        if ($actor->esEncargado()) {
            if ($this->user->id === $actor->id) {
                // No puede cambiarse su propio rol ni sucursal desde acá: una
                // sesión de encargado comprometida no debería poder
                // autopromoverse a Admin manipulando el request.
                $data['role'] = $this->user->role->value;
                $data['sucursal_id'] = $this->user->sucursal_id;
            } else {
                $data['sucursal_id'] = (string) $actor->sucursal_id;
            }
        }

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
        $actor = Auth::user();
        // Editando a un tercero, un encargado solo puede dejarlo en
        // Cajero/Vendedor y en su propia sucursal. Editándose a sí mismo ve
        // las opciones completas (no importa: save() ignora cualquier cambio
        // a su propio rol/sucursal, ver arriba).
        $restringir = $actor->esEncargado() && $this->user->id !== $actor->id;

        return view('livewire.users.edit', [
            'roles' => $restringir ? [Role::Vendedor, Role::Cajero] : Role::cases(),
            // Incluye la sucursal actual del usuario aunque esté inactiva, para
            // no romper el <select> si se desactivó después de asignarla.
            'sucursales' => $restringir
                ? Sucursal::where('id', $actor->sucursal_id)->get()
                : Sucursal::where('active', true)->orWhere('id', $this->user->sucursal_id)->orderBy('name')->get(),
            'editingSelf' => $this->user->id === Auth::id(),
        ]);
    }
}
