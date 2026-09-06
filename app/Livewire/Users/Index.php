<?php

namespace App\Livewire\Users;

use App\Enums\Role;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use ShowsToasts, WithPagination;

    public function delete(User $user): void
    {
        $actor = Auth::user();

        if ($user->id === $actor->id) {
            $this->toastError('No podés eliminar tu propio usuario mientras estás en sesión.');

            return;
        }

        // Un encargado solo borra Cajero/Vendedor de SU sucursal, sin
        // importar qué id le llegue por wire:click (mismo criterio que
        // Users\Edit::mount()).
        if ($actor->esEncargado() && ! ($user->sucursal_id === $actor->sucursal_id && in_array($user->role, [Role::Vendedor, Role::Cajero], true))) {
            $this->toastError('No podés eliminar este usuario.');

            return;
        }

        $activeAdmins = User::where('role', Role::Admin)->where('active', true)->count();

        if ($user->role === Role::Admin && $user->active && $activeAdmins <= 1) {
            $this->toastError('No se puede eliminar al último administrador activo.');

            return;
        }

        $user->delete();

        $this->toastSuccess('Usuario eliminado.');
    }

    public function render()
    {
        $actor = Auth::user();

        $users = User::with('sucursal')
            ->when($actor->esEncargado(), fn ($q) => $q->where(function ($outer) use ($actor) {
                $outer->where('id', $actor->id)
                    ->orWhere(function ($inner) use ($actor) {
                        $inner->where('sucursal_id', $actor->sucursal_id)->whereIn('role', [Role::Vendedor, Role::Cajero]);
                    });
            }))
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.users.index', [
            'users' => $users,
        ]);
    }
}
