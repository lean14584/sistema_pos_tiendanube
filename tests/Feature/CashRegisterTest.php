<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Product;
use App\Models\Provider;
use App\Models\ProviderPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CashRegisterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_can_open_and_close_a_session(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test('cash-register.index')
            ->set('openingAmount', '1000')
            ->call('openSession');

        $session = CashSession::first();
        $this->assertNotNull($session);
        $this->assertEquals('open', $session->status->value);
        $this->assertEquals($admin->id, $session->user_id);

        Livewire::actingAs($admin)
            ->test('cash-register.index')
            ->set('closingAmount', '1000')
            ->call('closeSession');

        $this->assertEquals('closed', $session->fresh()->status->value);
    }

    public function test_manual_movement_affects_summary(): void
    {
        $admin = $this->admin();
        $session = CashSession::create(['user_id' => $admin->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 1000]);

        Livewire::actingAs($admin)
            ->test('cash-register.index')
            ->set('movType', 'egreso')
            ->set('movConcept', 'Gastos de librería')
            ->set('movAmount', '150')
            ->call('addMovement');

        $this->assertDatabaseHas('cash_movements', ['session_id' => $session->id, 'concept' => 'Gastos de librería', 'amount' => 150]);
    }

    public function test_client_payment_creates_cash_ingreso_when_session_open(): void
    {
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);

        Livewire::actingAs($admin)
            ->test('clients.account', ['client' => $client])
            ->set('amount', '500')
            ->call('addPayment');

        $this->assertDatabaseHas('cash_movements', ['type' => 'ingreso', 'amount' => 500, 'source' => 'venta']);
    }

    public function test_provider_payment_creates_cash_egreso_when_session_open(): void
    {
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);
        $provider = Provider::create(['name' => 'Proveedor 1']);

        Livewire::actingAs($admin)
            ->test('providers.account', ['provider' => $provider])
            ->set('amount', '300')
            ->call('addPayment');

        $this->assertDatabaseHas('cash_movements', ['type' => 'egreso', 'amount' => 300, 'source' => 'compra']);
    }

    public function test_no_cash_movement_created_when_no_session_open(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);

        Livewire::actingAs($admin)
            ->test('clients.account', ['client' => $client])
            ->set('amount', '500')
            ->call('addPayment');

        $this->assertEquals(0, \App\Models\CashMovement::count());
    }

    public function test_deleting_client_payment_removes_linked_cash_movement(): void
    {
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);

        Livewire::actingAs($admin)
            ->test('clients.account', ['client' => $client])
            ->set('amount', '500')
            ->call('addPayment');

        $payment = ClientPayment::first();
        $this->assertEquals(1, \App\Models\CashMovement::count());

        Livewire::actingAs($admin)
            ->test('clients.account', ['client' => $client])
            ->call('deletePayment', $payment->id);

        $this->assertEquals(0, \App\Models\CashMovement::count());
    }

    public function test_manual_movement_can_be_deleted_from_the_open_session(): void
    {
        $admin = $this->admin();
        $session = CashSession::create(['user_id' => $admin->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);
        $movement = $session->movements()->create(['type' => 'egreso', 'concept' => 'Error de carga', 'amount' => 100, 'source' => 'manual', 'date' => now()]);

        Livewire::actingAs($admin)
            ->test('cash-register.index')
            ->call('deleteMovement', $movement->id);

        $this->assertModelMissing($movement);
    }

    public function test_no_se_puede_borrar_un_movimiento_de_una_caja_ya_cerrada(): void
    {
        // Bug de seguridad real: el método no scopeaba por sesión abierta,
        // así que cualquiera con acceso a Caja podía borrar el movimiento
        // manual de una caja de OTRO día ya cerrada, tapando un faltante.
        $admin = $this->admin();
        $closedSession = CashSession::create(['user_id' => $admin->id, 'status' => 'closed', 'opened_at' => now()->subDay(), 'closed_at' => now()->subDay(), 'opening_amount' => 0, 'closing_amount' => 0]);
        $movement = $closedSession->movements()->create(['type' => 'egreso', 'concept' => 'Faltante', 'amount' => 5000, 'source' => 'manual', 'date' => now()->subDay()]);

        // Sin ninguna caja abierta ahora mismo.
        Livewire::actingAs($admin)
            ->test('cash-register.index')
            ->call('deleteMovement', $movement->id);

        $this->assertModelExists($movement);
    }

    public function test_no_se_puede_borrar_un_movimiento_de_otra_sesion_aunque_haya_una_abierta(): void
    {
        $admin = $this->admin();
        $vieja = CashSession::create(['user_id' => $admin->id, 'status' => 'closed', 'opened_at' => now()->subDay(), 'closed_at' => now()->subDay(), 'opening_amount' => 0, 'closing_amount' => 0]);
        $movementVieja = $vieja->movements()->create(['type' => 'egreso', 'concept' => 'Faltante viejo', 'amount' => 5000, 'source' => 'manual', 'date' => now()->subDay()]);

        CashSession::create(['user_id' => $admin->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);

        Livewire::actingAs($admin)
            ->test('cash-register.index')
            ->call('deleteMovement', $movementVieja->id);

        $this->assertModelExists($movementVieja);
    }

    public function test_invoice_with_payment_creates_cash_ingreso(): void
    {
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        $product = Product::create(['name' => 'Servicio', 'price' => 800, 'iva_rate' => 0]);

        Livewire::actingAs($admin)
            ->test('invoices.create')
            ->set('client_id', (string) $client->id)
            ->call('addProductItem', $product->id)
            ->call('addPayment')
            ->call('save');

        $this->assertDatabaseHas('cash_movements', ['type' => 'ingreso', 'amount' => 800, 'source' => 'venta']);
    }
}
