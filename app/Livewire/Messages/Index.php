<?php

namespace App\Livewire\Messages;

use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public ?User $with = null;

    public string $body = '';

    public function mount(?User $with = null): void
    {
        $this->with = $with;
    }

    public function send(): void
    {
        $this->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        if (! $this->with) {
            return;
        }

        Message::create([
            'sender_id' => Auth::id(),
            'recipient_id' => $this->with->id,
            'body' => $this->body,
        ]);

        $this->body = '';
    }

    public function render()
    {
        $userId = Auth::id();

        $unreadCounts = Message::unreadFor($userId)
            ->selectRaw('sender_id, COUNT(*) as unread_count')
            ->groupBy('sender_id')
            ->pluck('unread_count', 'sender_id');

        $users = User::where('id', '!=', $userId)
            ->where('active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => tap($user, fn (User $u) => $u->unread_count = $unreadCounts[$user->id] ?? 0));

        $thread = collect();

        if ($this->with) {
            $thread = Message::where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)->where('recipient_id', $this->with->id);
            })->orWhere(function ($q) use ($userId) {
                $q->where('sender_id', $this->with->id)->where('recipient_id', $userId);
            })->orderBy('created_at')->get();

            Message::where('sender_id', $this->with->id)
                ->where('recipient_id', $userId)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        return view('livewire.messages.index', [
            'users' => $users,
            'thread' => $thread,
        ]);
    }
}
