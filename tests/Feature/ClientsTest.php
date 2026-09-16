<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Sucursal;
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

    /**
     * MEJORA: addPayment() no tenía lock — dos submits casi simultáneos
     * (doble clic) creaban dos ClientPayment del mismo cobro. Mismo patrón
     * de test que CobranzasTest::test_doble_clic_en_registrar_cobro_no_lo_duplica.
     */
    public function test_doble_clic_en_registrar_cobro_de_cuenta_corriente_no_lo_duplica(): void
    {
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'sucursal_id' => Sucursal::sole()->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);

        $component = Livewire::actingAs($admin)
            ->test('clients.account', ['client' => $client])
            ->set('amount', '3000');

        $component->call('addPayment')->assertHasNoErrors();
        $component->call('addPayment')->assertHasErrors('amount');

        $this->assertSame(1, ClientPayment::count(), 'No debería duplicarse el cobro al registrarlo dos veces.');
    }
}
