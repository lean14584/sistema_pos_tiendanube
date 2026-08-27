<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StockAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private function user(Role $role): User
    {
        return User::factory()->create(['role' => $role, 'active' => true]);
    }

    public function test_registrar_un_ajuste_actualiza_el_stock_y_queda_guardado_el_motivo(): void
    {
        $product = Product::create(['name' => 'Harina 1kg', 'price' => 800, 'stock' => 20]);

        Livewire::actingAs($this->user(Role::Admin))
            ->test('stock-adjustments.index')
            ->set('product_id', (string) $product->id)
            ->set('new_stock', '15')
            ->set('reason', 'rotura')
            ->set('notes', 'Se rompieron 5 paquetes en el depósito')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(15, $product->fresh()->stock);

        $adjustment = StockAdjustment::first();
        $this->assertNotNull($adjustment);
        $this->assertSame($product->id, $adjustment->product_id);
        $this->assertSame(20, $adjustment->previous_stock);
        $this->assertSame(15, $adjustment->new_stock);
        $this->assertSame(-5, $adjustment->delta);
        $this->assertSame('rotura', $adjustment->reason->value);
        $this->assertSame('Se rompieron 5 paquetes en el depósito', $adjustment->notes);
    }

    public function test_el_ajuste_tambien_genera_un_log_de_auditoria_del_producto(): void
    {
        $product = Product::create(['name' => 'Fideos 500g', 'price' => 900, 'stock' => 10]);
        AuditLog::query()->delete(); // limpiar el log del create de arriba

        Livewire::actingAs($this->user(Role::Admin))
            ->test('stock-adjustments.index')
            ->set('product_id', (string) $product->id)
            ->set('new_stock', '30')
            ->set('reason', 'conteo_fisico')
            ->call('save');

        $log = AuditLog::where('auditable_type', Product::class)->where('event', 'updated')->first();

        $this->assertNotNull($log);
        $this->assertSame(10, (int) $log->changes['stock']['old']);
        $this->assertSame(30, (int) $log->changes['stock']['new']);
    }

    public function test_no_se_puede_ajustar_a_stock_negativo(): void
    {
        $product = Product::create(['name' => 'Yerba 1kg', 'price' => 3000, 'stock' => 5]);

        Livewire::actingAs($this->user(Role::Admin))
            ->test('stock-adjustments.index')
            ->set('product_id', (string) $product->id)
            ->set('new_stock', '-3')
            ->set('reason', 'merma_robo')
            ->call('save')
            ->assertHasErrors(['new_stock']);

        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_cajero_no_puede_entrar_a_ajustes_de_stock_pero_vendedor_y_admin_si(): void
    {
        $this->actingAs($this->user(Role::Cajero))->get(route('stock-adjustments.index'))->assertForbidden();
        $this->actingAs($this->user(Role::Vendedor))->get(route('stock-adjustments.index'))->assertOk();
        $this->actingAs($this->user(Role::Admin))->get(route('stock-adjustments.index'))->assertOk();
    }

    public function test_el_historial_de_producto_muestra_altas_ediciones_y_ajustes_de_stock(): void
    {
        $admin = $this->user(Role::Admin);
        $product = Product::create(['name' => 'Aceite 900ml', 'price' => 2000, 'stock' => 10]);
        $product->update(['price' => 2200]);

        StockAdjustment::create([
            'product_id' => $product->id, 'user_id' => $admin->id,
            'previous_stock' => 10, 'new_stock' => 8,
            'reason' => 'vencimiento', 'notes' => 'Venció el 20/08',
        ]);

        $component = Livewire::actingAs($admin)->test('products.historial', ['product' => $product]);

        $component->assertSee('Alta')
            ->assertSee('Modificación')
            ->assertSee('Ajuste de stock')
            ->assertSee('Vencimiento')
            ->assertSee('Venció el 20/08')
            ->assertSee('2000')
            ->assertSee('2200');
    }
}
