<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PurchasesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    private function openCashSession(User $user): CashSession
    {
        return CashSession::create([
            'user_id' => $user->id,
            'sucursal_id' => Sucursal::sole()->id,
            'status' => 'open',
            'opened_at' => now(),
            'opening_amount' => 0,
        ]);
    }

    public function test_creating_a_purchase_increments_product_stock(): void
    {
        $provider = Provider::create(['name' => 'Proveedor 1']);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 5]);

        Livewire::actingAs($this->admin())
            ->test('purchases.create')
            ->set('provider_id', (string) $provider->id)
            ->call('addProductItem', $product->id)
            ->set('items.0.quantity', '3')
            ->call('save');

        $this->assertEquals(8, $product->fresh()->stock);
        $this->assertEquals(1, Purchase::count());
    }

    public function test_editing_a_purchase_adjusts_stock_delta_correctly(): void
    {
        $provider = Provider::create(['name' => 'Proveedor 1']);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 5]);

        $purchase = Purchase::create([
            'number' => 'COM-0001', 'provider_id' => $provider->id, 'tax_rate' => 0,
            'issue_date' => now(), 'due_date' => now()->addDays(15), 'status' => 'draft',
        ]);
        $purchase->items()->create(['product_id' => $product->id, 'description' => 'Notebook', 'quantity' => 3, 'unit_price' => 1000]);
        $product->increment('stock', 3); // simulate the +3 stock the purchase already applied

        $this->assertEquals(8, $product->fresh()->stock);

        Livewire::actingAs($this->admin())
            ->test('purchases.edit', ['purchase' => $purchase])
            ->set('items.0.quantity', '5')
            ->call('save');

        // Old +3 reverted, new +5 applied: 8 - 3 + 5 = 10
        $this->assertEquals(10, $product->fresh()->stock);
    }

    public function test_deleting_a_purchase_reverts_stock(): void
    {
        $provider = Provider::create(['name' => 'Proveedor 1']);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 5]);

        $purchase = Purchase::create([
            'number' => 'COM-0001', 'provider_id' => $provider->id,
            'issue_date' => now(), 'due_date' => now()->addDays(15), 'status' => 'draft',
        ]);
        $purchase->items()->create(['product_id' => $product->id, 'description' => 'Notebook', 'quantity' => 3, 'unit_price' => 1000]);
        $product->increment('stock', 3);

        Livewire::actingAs($this->admin())
            ->test('purchases.show', ['purchase' => $purchase])
            ->call('delete');

        $this->assertEquals(5, $product->fresh()->stock);
        $this->assertEquals(0, Purchase::count());
    }

    public function test_crear_compra_con_dos_metodos_de_pago_genera_un_movimiento_de_caja_por_cada_uno(): void
    {
        $admin = $this->admin();
        $this->openCashSession($admin);

        $provider = Provider::create(['name' => 'Proveedor 1']);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 5]);

        Livewire::actingAs($admin)
            ->test('purchases.create')
            ->set('provider_id', (string) $provider->id)
            ->call('addProductItem', $product->id)
            ->set('items.0.quantity', '1')
            ->set('items.0.unit_price', '1000')
            ->call('addPayment')
            ->set('payments.0.method', 'efectivo')
            ->set('payments.0.amount', '600')
            ->call('addPayment')
            ->set('payments.1.method', 'tarjeta')
            ->set('payments.1.amount', '400')
            ->call('save');

        $purchase = Purchase::first();
        $this->assertNotNull($purchase);
        $this->assertSame(2, PurchasePayment::count());
        $this->assertSame(2, CashMovement::count());
        $this->assertEqualsWithDelta(1000.0, (float) CashMovement::sum('amount'), 0.01);
        $this->assertTrue(CashMovement::where('type', 'egreso')->where('source', 'compra')->exists());
    }

    public function test_editar_compra_reemplaza_pagos_y_movimientos_de_caja(): void
    {
        $admin = $this->admin();
        $this->openCashSession($admin);

        $provider = Provider::create(['name' => 'Proveedor 1']);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 5]);

        $purchase = Purchase::create([
            'number' => 'COM-0001', 'provider_id' => $provider->id, 'tax_rate' => 0,
            'issue_date' => now(), 'due_date' => now()->addDays(15), 'status' => 'draft',
        ]);
        $purchase->items()->create(['product_id' => $product->id, 'description' => 'Notebook', 'quantity' => 1, 'unit_price' => 1000]);
        $product->increment('stock', 1);
        $oldPayment = $purchase->payments()->create(['method' => 'efectivo', 'amount' => 1000]);
        \App\Support\CashLinker::linkPurchasePayment($purchase, $oldPayment);

        $this->assertSame(1, CashMovement::count());

        Livewire::actingAs($admin)
            ->test('purchases.edit', ['purchase' => $purchase])
            ->set('payments.0.amount', '0') // deja de pagar en efectivo...
            ->call('addPayment')
            ->set('payments.1.method', 'transferencia')
            ->set('payments.1.amount', '1000') // ...y paga todo por transferencia
            ->call('save');

        $this->assertSame(1, PurchasePayment::count());
        $this->assertSame('transferencia', PurchasePayment::first()->method->value);
        $this->assertSame(1, CashMovement::count());
        $this->assertEqualsWithDelta(1000.0, (float) CashMovement::first()->amount, 0.01);
    }

    public function test_borrar_una_compra_revierte_los_movimientos_de_caja_de_sus_pagos(): void
    {
        $admin = $this->admin();
        $this->openCashSession($admin);

        $provider = Provider::create(['name' => 'Proveedor 1']);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 5]);

        $purchase = Purchase::create([
            'number' => 'COM-0001', 'provider_id' => $provider->id,
            'issue_date' => now(), 'due_date' => now()->addDays(15), 'status' => 'draft',
        ]);
        $purchase->items()->create(['product_id' => $product->id, 'description' => 'Notebook', 'quantity' => 1, 'unit_price' => 1000]);
        $payment = $purchase->payments()->create(['method' => 'efectivo', 'amount' => 1000]);
        \App\Support\CashLinker::linkPurchasePayment($purchase, $payment);

        $this->assertSame(1, CashMovement::count());

        Livewire::actingAs($admin)
            ->test('purchases.show', ['purchase' => $purchase])
            ->call('delete');

        $this->assertSame(0, CashMovement::count());
    }

    public function test_purchases_index_paginates_instead_of_loading_everything(): void
    {
        $provider = Provider::create(['name' => 'Proveedor 1']);
        for ($i = 1; $i <= 21; $i++) {
            Purchase::create([
                'number' => 'COM-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'provider_id' => $provider->id,
                'issue_date' => now(), 'due_date' => now()->addDays(15), 'status' => 'draft',
            ]);
        }

        $component = Livewire::actingAs($this->admin())->test('purchases.index');

        $this->assertCount(20, $component->viewData('purchases'));
        $this->assertEquals(2, $component->viewData('purchases')->lastPage());
    }
}
