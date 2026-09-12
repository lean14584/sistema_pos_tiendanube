<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MessagesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_muestra_los_mensajes_en_orden_cronologico(): void
    {
        $me = $this->admin();
        $other = User::factory()->create(['role' => Role::Admin, 'active' => true]);

        Message::create(['sender_id' => $me->id, 'recipient_id' => $other->id, 'body' => 'Primero']);
        Message::create(['sender_id' => $other->id, 'recipient_id' => $me->id, 'body' => 'Segundo']);
        Message::create(['sender_id' => $me->id, 'recipient_id' => $other->id, 'body' => 'Tercero']);

        $thread = Livewire::actingAs($me)
            ->test('messages.index', ['with' => $other])
            ->viewData('thread');

        $this->assertSame(['Primero', 'Segundo', 'Tercero'], $thread->pluck('body')->all());
    }

    public function test_un_chat_muy_largo_solo_trae_los_ultimos_200_mensajes_y_mantiene_el_orden(): void
    {
        $me = $this->admin();
        $other = User::factory()->create(['role' => Role::Admin, 'active' => true]);

        // 205 mensajes, con el body igual al número de orden en que se
        // mandó, para poder verificar que se recortan los más viejos (no los
        // más nuevos) y que el orden cronológico se mantiene tras el
        // recorte. 'created_at' no es fillable en Message, así que el orden
        // real queda dado por el id autoincremental (el mismo desempate que
        // usa la query de producción para mensajes con igual timestamp).
        for ($i = 1; $i <= 205; $i++) {
            Message::create(['sender_id' => $me->id, 'recipient_id' => $other->id, 'body' => "msg-{$i}"]);
        }

        $thread = Livewire::actingAs($me)
            ->test('messages.index', ['with' => $other])
            ->viewData('thread');

        $this->assertCount(200, $thread);
        $this->assertSame('msg-6', $thread->first()->body); // se descartaron msg-1..msg-5, los más viejos
        $this->assertSame('msg-205', $thread->last()->body); // el más nuevo sigue estando
    }
}
