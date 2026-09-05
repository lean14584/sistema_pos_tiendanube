<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreditLimitTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_pos_bloquea_venta_a_cuenta_que_supera_el_limite(): void
    {
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'sucursal_id' => Sucursal::sole()->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);

        $client = Client::create(['name' => 'Fiado', 'email' => 'f@test.com', 'credit_limit' => 5000]);
        $product = Product::create(['name' => 'Caja', 'price' => 8000, 'iva_rate' => 0, 'stock' => 10]);

        // Venta de $8000 sin pago -> saldo 8000 > limite 5000 -> se bloquea.
        Livewire::actingAs($admin)->test('pos.index')
            ->call('addProduct', $product->id)
            ->set('client_id', $client->id)
            ->set('printOnSale', false)
            ->call('cobrar')
            ->assertHasErrors('client_id');

        $this->assertEquals(0, Invoice::count());
    }

    public function test_pos_permite_si_paga_lo_suficiente_para_no_pasar_el_limite(): void
    {
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'sucursal_id' => Sucursal::sole()->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);

        $client = Client::create(['name' => 'Fiado', 'email' => 'f@test.com', 'credit_limit' => 5000]);
        $product = Product::create(['name' => 'Caja', 'price' => 8000, 'iva_rate' => 0, 'stock' => 10]);

        // Paga 4000; queda 4000 a cuenta (< 5000) -> permitido.
        Livewire::actingAs($admin)->test('pos.index')
            ->call('addProduct', $product->id)
            ->set('client_id', $client->id)
            ->call('addPayment')
            ->set('cart.0.discount', 0)
            ->set('payments.0.amount', '4000')
            ->set('printOnSale', false)
            ->call('cobrar')
            ->assertHasNoErrors();

        $this->assertEquals(1, Invoice::count());
    }

    public function test_sin_limite_no_bloquea(): void
    {
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'sucursal_id' => Sucursal::sole()->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);

        $client = Client::create(['name' => 'Sin Limite', 'email' => 's@test.com']); // credit_limit null
        $product = Product::create(['name' => 'Caja', 'price' => 99999, 'iva_rate' => 0, 'stock' => 10]);

        Livewire::actingAs($admin)->test('pos.index')
            ->call('addProduct', $product->id)
            ->set('client_id', $client->id)
            ->set('printOnSale', false)
            ->call('cobrar')
            ->assertHasNoErrors();

        $this->assertEquals(1, Invoice::count());
    }

    public function test_excesoDeCredito_considera_el_saldo_previo(): void
    {
        $client = Client::create(['name' => 'C', 'email' => 'c@test.com', 'credit_limit' => 10000]);
        // Saldo previo de 7000 (factura impaga).
        $inv = Invoice::create(['number' => 'FAC-1', 'client_id' => $client->id, 'tax_rate' => 0, 'issue_date' => now(), 'due_date' => now(), 'status' => 'pending']);
        $inv->items()->create(['description' => 'x', 'quantity' => 1, 'unit_price' => 7000]);

        // Nueva deuda de 4000 -> 11000 > 10000 -> mensaje.
        $this->assertNotNull($client->excesoDeCredito(4000));
        // Nueva deuda de 2000 -> 9000 <= 10000 -> ok.
        $this->assertNull($client->fresh()->excesoDeCredito(2000));
    }
}
