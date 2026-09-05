<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockTransfer;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_crear_un_envio_solo_descuenta_del_origen_queda_pendiente(): void
    {
        $principal = Sucursal::sole();
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);

        $product = Product::create(['name' => 'Yerba', 'price' => 3000, 'stock' => 10]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => $principal->id, 'stock' => 10]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => $norte->id, 'stock' => 0]);

        Livewire::actingAs($this->admin())
            ->test('stock-transfers.index')
            ->set('from_sucursal_id', (string) $principal->id)
            ->set('to_sucursal_id', (string) $norte->id)
            ->call('addProductItem', $product->id)
            ->set('items.0.quantity', '4')
            ->call('save')
            ->assertHasNoErrors();

        // Descontado en origen, TODAVÍA NO acreditado en destino (queda
        // pendiente de que confirmen la recepción).
        $this->assertSame(6, $product->stockEnSucursal($principal->id));
        $this->assertSame(0, $product->stockEnSucursal($norte->id));
        $this->assertSame(6, $product->fresh()->stock);

        $transfer = StockTransfer::first();
        $this->assertNotNull($transfer);
        $this->assertSame('pendiente', $transfer->status->value);
        $this->assertSame($principal->id, $transfer->from_sucursal_id);
        $this->assertSame($norte->id, $transfer->to_sucursal_id);
        $this->assertSame(1, $transfer->items()->count());
        $this->assertSame(4, $transfer->items()->first()->quantity);
        $this->assertNull($transfer->items()->first()->quantity_received);
    }

    public function test_confirmar_recepcion_completa_acredita_el_destino(): void
    {
        $principal = Sucursal::sole();
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);
        $admin = $this->admin();

        $product = Product::create(['name' => 'Yerba', 'price' => 3000, 'stock' => 10]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => $principal->id, 'stock' => 10]);

        Livewire::actingAs($admin)
            ->test('stock-transfers.index')
            ->set('from_sucursal_id', (string) $principal->id)
            ->set('to_sucursal_id', (string) $norte->id)
            ->call('addProductItem', $product->id)
            ->set('items.0.quantity', '4')
            ->call('save');

        $transfer = StockTransfer::first();

        Livewire::actingAs($admin)
            ->test('stock-transfers.show', ['transfer' => $transfer])
            ->set('received.0', '4')
            ->call('confirmarRecepcion')
            ->assertHasNoErrors();

        $this->assertSame(4, $product->stockEnSucursal($norte->id));
        $this->assertSame(10, $product->fresh()->stock); // 6 + 4
        $this->assertSame('recibido', $transfer->fresh()->status->value);
        $this->assertNotNull($transfer->fresh()->received_at);
        $this->assertSame($admin->id, $transfer->fresh()->received_by_user_id);
    }

    public function test_confirmar_recepcion_parcial_por_rotura_no_acredita_la_diferencia(): void
    {
        $principal = Sucursal::sole();
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);
        $admin = $this->admin();

        $product = Product::create(['name' => 'Yerba', 'price' => 3000, 'stock' => 10]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => $principal->id, 'stock' => 10]);

        Livewire::actingAs($admin)
            ->test('stock-transfers.index')
            ->set('from_sucursal_id', (string) $principal->id)
            ->set('to_sucursal_id', (string) $norte->id)
            ->call('addProductItem', $product->id)
            ->set('items.0.quantity', '10')
            ->call('save');

        $transfer = StockTransfer::first();

        // Se enviaron 10, llegaron 8 (2 se rompieron en el traslado).
        Livewire::actingAs($admin)
            ->test('stock-transfers.show', ['transfer' => $transfer])
            ->set('received.0', '8')
            ->call('confirmarRecepcion')
            ->assertHasNoErrors();

        $this->assertSame(8, $product->stockEnSucursal($norte->id));
        $this->assertSame(8, $product->fresh()->stock); // 0 en origen + 8 en destino, las otras 2 se perdieron
        $this->assertSame(8, $transfer->items()->first()->quantity_received);
    }

    public function test_no_se_puede_confirmar_recepcion_por_mas_de_lo_enviado(): void
    {
        $principal = Sucursal::sole();
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);
        $admin = $this->admin();

        $product = Product::create(['name' => 'Yerba', 'price' => 3000, 'stock' => 10]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => $principal->id, 'stock' => 10]);

        Livewire::actingAs($admin)
            ->test('stock-transfers.index')
            ->set('from_sucursal_id', (string) $principal->id)
            ->set('to_sucursal_id', (string) $norte->id)
            ->call('addProductItem', $product->id)
            ->set('items.0.quantity', '4')
            ->call('save');

        $transfer = StockTransfer::first();

        Livewire::actingAs($admin)
            ->test('stock-transfers.show', ['transfer' => $transfer])
            ->set('received.0', '99')
            ->call('confirmarRecepcion')
            ->assertHasErrors(['received.0']);

        $this->assertSame('pendiente', $transfer->fresh()->status->value);
        $this->assertSame(0, $product->stockEnSucursal($norte->id));
    }

    public function test_solo_alguien_parado_en_el_destino_o_un_admin_puede_confirmar_recepcion(): void
    {
        $principal = Sucursal::sole();
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);
        $admin = $this->admin();
        $vendedorPrincipal = User::factory()->create(['role' => Role::Vendedor, 'active' => true, 'sucursal_id' => $principal->id]);

        $product = Product::create(['name' => 'Yerba', 'price' => 3000, 'stock' => 10]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => $principal->id, 'stock' => 10]);

        Livewire::actingAs($admin)
            ->test('stock-transfers.index')
            ->set('from_sucursal_id', (string) $principal->id)
            ->set('to_sucursal_id', (string) $norte->id)
            ->call('addProductItem', $product->id)
            ->set('items.0.quantity', '4')
            ->call('save');

        $transfer = StockTransfer::first();

        // El vendedor del ORIGEN (no del destino) no puede confirmar.
        Livewire::actingAs($vendedorPrincipal)
            ->test('stock-transfers.show', ['transfer' => $transfer])
            ->assertSet('puedeConfirmar', false);
    }

    public function test_no_se_puede_enviar_mas_de_lo_que_hay_en_el_origen(): void
    {
        $principal = Sucursal::sole();
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);

        $product = Product::create(['name' => 'Yerba', 'price' => 3000, 'stock' => 5]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => $principal->id, 'stock' => 5]);

        Livewire::actingAs($this->admin())
            ->test('stock-transfers.index')
            ->set('from_sucursal_id', (string) $principal->id)
            ->set('to_sucursal_id', (string) $norte->id)
            ->call('addProductItem', $product->id)
            ->set('items.0.quantity', '20')
            ->call('save')
            ->assertHasErrors(['items.0.quantity']);

        $this->assertSame(5, $product->stockEnSucursal($principal->id));
        $this->assertSame(0, StockTransfer::count());
    }

    public function test_origen_y_destino_no_pueden_ser_la_misma_sucursal(): void
    {
        $principal = Sucursal::sole();
        Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);
        $product = Product::create(['name' => 'Yerba', 'price' => 3000, 'stock' => 5]);

        Livewire::actingAs($this->admin())
            ->test('stock-transfers.index')
            ->set('from_sucursal_id', (string) $principal->id)
            ->set('to_sucursal_id', (string) $principal->id)
            ->call('addProductItem', $product->id)
            ->call('save')
            ->assertHasErrors(['to_sucursal_id']);
    }

    public function test_un_vendedor_no_puede_elegir_el_origen_queda_fijo_en_su_sucursal(): void
    {
        $principal = Sucursal::sole();
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);
        $vendedor = User::factory()->create(['role' => Role::Vendedor, 'active' => true, 'sucursal_id' => $norte->id]);

        $product = Product::create(['name' => 'Yerba', 'price' => 3000, 'stock' => 10]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => $norte->id, 'stock' => 10]);

        // El vendedor intenta forzar el origen a "Principal" (donde no está),
        // pero como no puede elegir origen, save() usa su propia sucursal igual.
        Livewire::actingAs($vendedor)
            ->test('stock-transfers.index')
            ->set('from_sucursal_id', (string) $principal->id)
            ->set('to_sucursal_id', (string) $principal->id)
            ->call('addProductItem', $product->id)
            ->call('save');

        $transfer = StockTransfer::first();
        $this->assertNotNull($transfer);
        $this->assertSame($norte->id, $transfer->from_sucursal_id);
    }

    public function test_cajero_no_puede_entrar_a_envios_de_mercaderia(): void
    {
        $cajero = User::factory()->create(['role' => Role::Cajero, 'active' => true]);

        $this->actingAs($cajero)->get(route('stock-transfers.index'))->assertForbidden();
    }
}
