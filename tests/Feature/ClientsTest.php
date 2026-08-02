<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_clients_index_paginates_instead_of_loading_everything(): void
    {
        for ($i = 1; $i <= 21; $i++) {
            Client::create(['name' => "Cliente {$i}", 'email' => "cliente{$i}@test.com"]);
        }

        $component = Livewire::actingAs($this->admin())->test('clients.index');

        $this->assertCount(20, $component->viewData('clients'));
        $this->assertEquals(2, $component->viewData('clients')->lastPage());
    }
}
