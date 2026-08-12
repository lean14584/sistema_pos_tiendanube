<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CashSession;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PosTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_agrega_un_producto_por_codigo_de_barras(): void
    {
        Product::create(['name' => 'Gaseosa', 'sku' => '7791234567890', 'price' => 800, 'iva_rate' => 21, 'stock' => 10]);

        $pos = Livewire::actingAs($this->admin())
            ->test('pos.index')
            ->set('barcode', '7791234567890')
            ->call('addByBarcode');

        $this->assertCount(1, $pos->get('cart'));
        $pos->assertSet('barcode', ''); // se limpia para el próximo escaneo
    }

    public function test_codigo_inexistente_muestra_error(): void
    {
        Livewire::actingAs($this->admin())
            ->test('pos.index')
            ->set('barcode', 'NO-EXISTE')
            ->call('addByBarcode')
            ->assertHasErrors('barcode');
    }

    public function test_cobrar_crea_la_venta_descuenta_stock_y_registra_caja(): void
    {
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);

        $product = Product::create(['name' => 'Alfajor', 'price' => 500, 'iva_rate' => 0, 'stock' => 5]);

        Livewire::actingAs($admin)
            ->test('pos.index')
            ->call('addProduct', $product->id)
            ->call('addProduct', $product->id) // cantidad 2
            ->set('paymentMethod', 'efectivo')
            ->set('printOnSale', false)
            ->call('cobrar');

        $this->assertDatabaseHas('invoices', ['status' => 'paid']);
        $this->assertEquals(3, $product->fresh()->stock); // 5 - 2
        $this->assertDatabaseHas('cash_movements', ['type' => 'ingreso', 'amount' => 1000, 'source' => 'venta']);
    }
}
