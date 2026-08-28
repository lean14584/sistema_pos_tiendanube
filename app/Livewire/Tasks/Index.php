<?php

namespace App\Livewire\Tasks;

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    use ShowsToasts;

    #[Url]
    public string $filter = 'all';

    public function updateStatus(Task $task, string $status): void
    {
        $user = Auth::user();

        abort_unless($user->role === Role::Admin || $task->assigned_to === $user->id, 403);

        $task->update(['status' => $status]);
    }

    public function delete(Task $task): void
    {
        abort_unless(Auth::user()->role === Role::Admin, 403);

        $task->delete();

        $this->toastSuccess('Tarea eliminada.');
    }

    public function render()
    {
        $user = Auth::user();
        $isAdmin = $user->role === Role::Admin;

        $tasks = Task::with('assignee', 'assigner')
            ->when(! $isAdmin, fn ($q) => $q->where('assigned_to', $user->id))
            ->when($this->filter !== 'all', fn ($q) => $q->where('status', $this->filter))
            ->latest()
            ->get();

        return view('livewire.tasks.index', [
            'tasks' => $tasks,
            'isAdmin' => $isAdmin,
            'statuses' => TaskStatus::cases(),
        ]);
    }
}
