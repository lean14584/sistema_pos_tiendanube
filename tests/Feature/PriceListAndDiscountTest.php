<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PriceListAndDiscountTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_seed_crea_lista_minorista_por_defecto(): void
    {
        $default = PriceList::default();

        $this->assertNotNull($default);
        $this->assertSame('Minorista', $default->name);
        $this->assertEquals(0, (float) $default->adjustment_percent);
    }

    public function test_price_for_list_aplica_el_porcentaje(): void
    {
        $mayorista = PriceList::create(['name' => 'Mayorista', 'adjustment_percent' => -15, 'is_default' => false, 'active' => true]);
        $tarjeta = PriceList::create(['name' => 'Tarjeta', 'adjustment_percent' => 10, 'is_default' => false, 'active' => true]);
        $product = Product::create(['name' => 'X', 'price' => 1000, 'iva_rate' => 21, 'stock' => 5]);

        $this->assertEquals(850.0, $product->priceForList($mayorista));
        $this->assertEquals(1100.0, $product->priceForList($tarjeta));
        $this->assertEquals(1000.0, $product->priceForList(null)); // sin lista = precio base
    }

    public function test_line_total_e_iva_aplican_descuento(): void
    {
        $invoice = Invoice::create([
            'number' => 'FAC-9001', 'client_id' => Client::consumidorFinal()->id, 'tax_rate' => 0,
            'issue_date' => now(), 'due_date' => now(), 'status' => 'pending',
        ]);

        $item = $invoice->items()->create([
            'description' => 'Producto', 'quantity' => 2, 'unit_price' => 100,
            'discount_percent' => 10, 'iva_rate' => 21,
        ]);

        // 2 x 100 = 200, menos 10% = 180
        $this->assertEquals(180.0, round($item->line_total, 2));
        // IVA sobre el neto con descuento: 180 x 21% = 37.80
        $this->assertEquals(37.80, round($item->iva_amount, 2));
        // Total de la factura = neto + iva = 217.80
        $this->assertEquals(217.80, round((float) $invoice->fresh()->total, 2));
    }

    public function test_pos_toma_el_precio_de_la_lista_del_cliente(): void
    {
        $mayorista = PriceList::create(['name' => 'Mayorista', 'adjustment_percent' => -20, 'is_default' => false, 'active' => true]);
        $client = Client::create(['name' => 'Distri', 'email' => 'distri@test.com', 'price_list_id' => $mayorista->id]);
        $product = Product::create(['name' => 'Fideos', 'price' => 1000, 'iva_rate' => 0, 'stock' => 50]);

        $pos = Livewire::actingAs($this->admin())
            ->test('pos.index')
            ->set('client_id', $client->id) // dispara updatedClientId -> aplica la lista
            ->call('addProduct', $product->id);

        $cart = $pos->get('cart');
        $this->assertEquals(800.0, $cart[0]['unit_price']); // 1000 - 20%
    }

    public function test_pos_descuento_por_linea_baja_el_total_cobrado(): void
    {
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);

        $product = Product::create(['name' => 'Arroz', 'price' => 1000, 'iva_rate' => 0, 'stock' => 20]);

        Livewire::actingAs($admin)
            ->test('pos.index')
            ->call('addProduct', $product->id)
            ->set('cart.0.discount', 25) // 25% off => 750
            ->call('addPayment')          // prellena efectivo con el total con descuento
            ->set('printOnSale', false)
            ->call('cobrar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('invoice_items', ['description' => 'Arroz', 'discount_percent' => 25]);
        $this->assertDatabaseHas('cash_movements', ['type' => 'ingreso', 'amount' => 750, 'source' => 'venta']);
    }

    public function test_admin_puede_crear_lista_y_hacerla_predeterminada(): void
    {
        $component = Livewire::actingAs($this->admin())
            ->test('price-lists.index')
            ->set('name', 'Mayorista')
            ->set('adjustment_percent', '-15')
            ->call('save');

        $this->assertDatabaseHas('price_lists', ['name' => 'Mayorista', 'adjustment_percent' => -15]);

        $nueva = PriceList::where('name', 'Mayorista')->first();
        $component->call('makeDefault', $nueva->id);

        $this->assertTrue($nueva->fresh()->is_default);
        $this->assertFalse(PriceList::where('name', 'Minorista')->first()->is_default);
    }
}
