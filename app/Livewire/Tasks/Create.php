<?php

namespace App\Livewire\Tasks;

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public string $title = '';

    public string $description = '';

    public string $assigned_to = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->role === Role::Admin, 403, 'Solo un administrador puede crear tareas.');
    }

    public function save(): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to' => ['required', 'exists:users,id'],
        ]);

        Task::create([
            ...$data,
            'assigned_by' => Auth::id(),
            'status' => TaskStatus::Pending->value,
        ]);

        $this->redirect(route('tasks.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.tasks.create', [
            'users' => User::where('id', '!=', Auth::id())->where('active', true)->orderBy('name')->get(),
        ]);
    }
}
