<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Provider;
use App\Models\ProviderPayment;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CurrentAccountTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_client_account_shows_only_non_draft_invoices_and_computes_balance(): void
    {
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);

        $draft = Invoice::create([
            'number' => 'FAC-0001', 'client_id' => $client->id, 'tax_rate' => 0,
            'issue_date' => now(), 'due_date' => now()->addDays(15), 'status' => 'draft',
        ]);
        $draft->items()->create(['description' => 'x', 'quantity' => 1, 'unit_price' => 999]);

        $pending = Invoice::create([
            'number' => 'FAC-0002', 'client_id' => $client->id, 'tax_rate' => 0,
            'issue_date' => now(), 'due_date' => now()->addDays(15), 'status' => 'pending',
        ]);
        $pending->items()->create(['description' => 'x', 'quantity' => 1, 'unit_price' => 500]);

        Livewire::actingAs($this->admin())
            ->test('clients.account', ['client' => $client])
            ->assertDontSee('FAC-0001')
            ->assertSee('FAC-0002')
            ->assertSee('500.00');
    }

    public function test_can_register_client_payment_and_delete_it(): void
    {
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);

        $component = Livewire::actingAs($this->admin())
            ->test('clients.account', ['client' => $client])
            ->set('amount', '150')
            ->set('method', 'efectivo')
            ->call('addPayment');

        $this->assertDatabaseHas('client_payments', ['client_id' => $client->id, 'amount' => 150]);

        $payment = ClientPayment::first();
        $component->call('deletePayment', $payment->id);

        $this->assertDatabaseMissing('client_payments', ['id' => $payment->id]);
    }

    public function test_provider_saldo_cuenta_corriente_resta_pagos_de_compra_y_a_cuenta(): void
    {
        // Antes no existía este método (a diferencia de Client): la fórmula
        // se reimplementaba a mano en Providers/Account.php.
        $provider = Provider::create(['name' => 'Proveedor 1']);
        $product = Product::create(['name' => 'P', 'price' => 1]);

        $purchase = Purchase::create([
            'number' => 'COM-0001', 'provider_id' => $provider->id, 'tax_rate' => 0,
            'issue_date' => now(), 'due_date' => now()->addDays(15), 'status' => 'pending',
        ]);
        $purchase->items()->create(['product_id' => $product->id, 'description' => 'x', 'quantity' => 1, 'unit_price' => 1000]);
        $purchase->payments()->create(['method' => 'efectivo', 'amount' => 200, 'date' => now()]);

        ProviderPayment::create(['provider_id' => $provider->id, 'date' => now(), 'amount' => 300, 'method' => 'efectivo']);

        // Debe: 1000 - 200 (pagado al momento) - 300 (pago a cuenta) = 500.
        $this->assertEqualsWithDelta(500.0, $provider->fresh()->saldoCuentaCorriente(), 0.01);
    }

    public function test_provider_account_computes_balance_from_purchases_and_payments(): void
    {
        $provider = Provider::create(['name' => 'Proveedor 1']);
        $purchase = Purchase::create([
            'number' => 'COM-0001', 'provider_id' => $provider->id, 'tax_rate' => 0,
            'issue_date' => now(), 'due_date' => now()->addDays(15), 'status' => 'pending',
        ]);
        $purchase->items()->create(['product_id' => Product::create(['name' => 'P', 'price' => 1])->id, 'description' => 'x', 'quantity' => 1, 'unit_price' => 1000]);

        Livewire::actingAs($this->admin())
            ->test('providers.account', ['provider' => $provider])
            ->assertSee('COM-0001')
            ->assertSee('Les debemos');
    }
}
