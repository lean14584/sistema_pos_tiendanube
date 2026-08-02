<?php

namespace App\Livewire\Users;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public function delete(User $user): void
    {
        if ($user->id === Auth::id()) {
            session()->flash('error', 'No podés eliminar tu propio usuario mientras estás en sesión.');

            return;
        }

        $activeAdmins = User::where('role', Role::Admin)->where('active', true)->count();

        if ($user->role === Role::Admin && $user->active && $activeAdmins <= 1) {
            session()->flash('error', 'No se puede eliminar al último administrador activo.');

            return;
        }

        $user->delete();
    }

    public function render()
    {
        return view('livewire.users.index', [
            'users' => User::orderBy('name')->paginate(20),
        ]);
    }
}
