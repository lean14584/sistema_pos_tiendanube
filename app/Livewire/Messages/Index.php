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
            // Se trae solo lo más reciente (recién en desc + limit, después
            // se da vuelta): un chat interno entre dos usuarios que lleva
            // años puede acumular miles de mensajes, y acá no hace falta
            // paginar hacia atrás como en un historial — alcanza con lo
            // último para dar contexto. El desempate por id es necesario:
            // dos mensajes mandados dentro del mismo segundo tienen igual
            // created_at, y sin un desempate determinístico el orden entre
            // ellos queda a criterio del motor de base de datos.
            $thread = Message::where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)->where('recipient_id', $this->with->id);
            })->orWhere(function ($q) use ($userId) {
                $q->where('sender_id', $this->with->id)->where('recipient_id', $userId);
            })->orderByDesc('created_at')->orderByDesc('id')->limit(200)->get()->reverse()->values();

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
