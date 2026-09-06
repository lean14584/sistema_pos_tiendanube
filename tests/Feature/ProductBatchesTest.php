<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductStock;
use App\Models\Provider;
use App\Models\StockAdjustment;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductBatchesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_recibir_una_compra_con_vencimiento_crea_un_lote(): void
    {
        $provider = Provider::create(['name' => 'Proveedor 1']);
        $product = Product::create(['name' => 'Yogur', 'price' => 500, 'stock' => 0]);

        Livewire::actingAs($this->admin())
            ->test('purchases.create')
            ->set('provider_id', (string) $provider->id)
            ->call('addProductItem', $product->id)
            ->set('items.0.quantity', '10')
            ->set('items.0.batch_number', 'L-001')
            ->set('items.0.expiration_date', now()->addDays(5)->toDateString())
            ->call('save')
            ->assertHasNoErrors();

        $batch = ProductBatch::sole();
        $this->assertSame($product->id, $batch->product_id);
        $this->assertSame(Sucursal::sole()->id, $batch->sucursal_id);
        $this->assertSame('L-001', $batch->batch_number);
        $this->assertEquals(10, $batch->quantity_received);
        $this->assertEquals(10, $batch->quantity_remaining);
        $this->assertSame('por_vencer', $batch->status->value);
    }

    public function test_un_item_de_compra_sin_vencimiento_no_crea_lote(): void
    {
        $provider = Provider::create(['name' => 'Proveedor 1']);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 0]);

        Livewire::actingAs($this->admin())
            ->test('purchases.create')
            ->set('provider_id', (string) $provider->id)
            ->call('addProductItem', $product->id)
            ->set('items.0.quantity', '3')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(0, ProductBatch::count());
    }

    public function test_dar_de_baja_un_lote_completo_descuenta_stock_y_lo_marca_consumido(): void
    {
        $sucursal = Sucursal::sole();
        $product = Product::create(['name' => 'Yogur', 'price' => 500, 'stock' => 10]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => $sucursal->id, 'stock' => 10]);

        $batch = ProductBatch::create([
            'product_id' => $product->id,
            'sucursal_id' => $sucursal->id,
            'batch_number' => 'L-002',
            'quantity_received' => 10,
            'quantity_remaining' => 10,
            'expiration_date' => now()->subDay()->toDateString(),
        ]);

        Livewire::actingAs($this->admin())
            ->test('product-batches.index')
            ->call('abrirBaja', $batch->id)
            ->set('bajaCantidad', '10')
            ->call('confirmarBaja')
            ->assertHasNoErrors();

        $this->assertSame(0, $product->stockEnSucursal($sucursal->id));
        $this->assertEquals(0, $batch->fresh()->quantity_remaining);
        $this->assertNotNull($batch->fresh()->written_off_at);

        $adjustment = StockAdjustment::sole();
        $this->assertSame('vencimiento', $adjustment->reason->value);
        $this->assertSame(10, $adjustment->previous_stock);
        $this->assertSame(0, $adjustment->new_stock);
    }

    public function test_dar_de_baja_parcial_deja_el_lote_activo(): void
    {
        $sucursal = Sucursal::sole();
        $product = Product::create(['name' => 'Yogur', 'price' => 500, 'stock' => 10]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => $sucursal->id, 'stock' => 10]);

        $batch = ProductBatch::create([
            'product_id' => $product->id,
            'sucursal_id' => $sucursal->id,
            'quantity_received' => 10,
            'quantity_remaining' => 10,
            'expiration_date' => now()->subDay()->toDateString(),
        ]);

        Livewire::actingAs($this->admin())
            ->test('product-batches.index')
            ->call('abrirBaja', $batch->id)
            ->set('bajaCantidad', '4')
            ->call('confirmarBaja')
            ->assertHasNoErrors();

        $this->assertSame(6, $product->stockEnSucursal($sucursal->id));
        $this->assertEquals(6, $batch->fresh()->quantity_remaining);
        $this->assertNull($batch->fresh()->written_off_at);
    }

    public function test_no_se_puede_dar_de_baja_mas_de_lo_que_queda_en_el_lote(): void
    {
        $sucursal = Sucursal::sole();
        $product = Product::create(['name' => 'Yogur', 'price' => 500, 'stock' => 10]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => $sucursal->id, 'stock' => 10]);

        $batch = ProductBatch::create([
            'product_id' => $product->id,
            'sucursal_id' => $sucursal->id,
            'quantity_received' => 10,
            'quantity_remaining' => 10,
            'expiration_date' => now()->subDay()->toDateString(),
        ]);

        Livewire::actingAs($this->admin())
            ->test('product-batches.index')
            ->call('abrirBaja', $batch->id)
            ->set('bajaCantidad', '99')
            ->call('confirmarBaja')
            ->assertHasErrors(['bajaCantidad']);

        $this->assertEquals(10, $batch->fresh()->quantity_remaining);
        $this->assertSame(10, $product->stockEnSucursal($sucursal->id));
    }

    public function test_un_vendedor_no_puede_dar_de_baja_un_lote_de_otra_sucursal(): void
    {
        $principal = Sucursal::sole();
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);
        $vendedor = User::factory()->create(['role' => Role::Vendedor, 'active' => true, 'sucursal_id' => $norte->id]);

        $product = Product::create(['name' => 'Yogur', 'price' => 500, 'stock' => 10]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => $principal->id, 'stock' => 10]);

        $batch = ProductBatch::create([
            'product_id' => $product->id,
            'sucursal_id' => $principal->id,
            'quantity_received' => 10,
            'quantity_remaining' => 10,
            'expiration_date' => now()->subDay()->toDateString(),
        ]);

        Livewire::actingAs($vendedor)
            ->test('product-batches.index')
            ->call('abrirBaja', $batch->id)
            ->set('bajaCantidad', '10')
            ->call('confirmarBaja');

        $this->assertEquals(10, $batch->fresh()->quantity_remaining);
        $this->assertSame(10, $product->stockEnSucursal($principal->id));
        $this->assertSame(0, StockAdjustment::count());
    }

    public function test_cajero_no_puede_entrar_a_lotes_y_vencimientos(): void
    {
        $cajero = User::factory()->create(['role' => Role::Cajero, 'active' => true]);

        $this->actingAs($cajero)->get(route('product-batches.index'))->assertForbidden();
    }

    public function test_scope_expiring_within_y_estado_del_lote(): void
    {
        $sucursal = Sucursal::sole();
        $product = Product::create(['name' => 'Yogur', 'price' => 500, 'stock' => 0]);

        $vencido = ProductBatch::create([
            'product_id' => $product->id, 'sucursal_id' => $sucursal->id,
            'quantity_received' => 5, 'quantity_remaining' => 5,
            'expiration_date' => now()->subDays(2)->toDateString(),
        ]);
        $porVencer = ProductBatch::create([
            'product_id' => $product->id, 'sucursal_id' => $sucursal->id,
            'quantity_received' => 5, 'quantity_remaining' => 5,
            'expiration_date' => now()->addDays(3)->toDateString(),
        ]);
        $ok = ProductBatch::create([
            'product_id' => $product->id, 'sucursal_id' => $sucursal->id,
            'quantity_received' => 5, 'quantity_remaining' => 5,
            'expiration_date' => now()->addDays(30)->toDateString(),
        ]);

        $this->assertSame('vencido', $vencido->status->value);
        $this->assertSame('por_vencer', $porVencer->status->value);
        $this->assertSame('ok', $ok->status->value);

        $this->assertSame(2, ProductBatch::active()->expiringWithin(ProductBatch::DIAS_ALERTA)->count());
    }
}
