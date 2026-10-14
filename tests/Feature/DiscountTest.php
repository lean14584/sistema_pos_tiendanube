<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CashSession;
use App\Models\CompanySettings;
use App\Models\Discount;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DiscountTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    private function abrirCaja(User $user): void
    {
        CashSession::create(['user_id' => $user->id, 'sucursal_id' => Sucursal::sole()->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);
    }

    // ---- ABM ----

    public function test_crear_descuento_porcentaje_desde_la_pantalla(): void
    {
        Livewire::actingAs($this->admin())
            ->test('discounts.index')
            ->set('name', 'Día de la Madre')
            ->set('type', 'percent')
            ->set('value', '15')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('discounts', [
            'name' => 'Día de la Madre', 'type' => 'percent', 'value' => 15, 'active' => true,
        ]);
    }

    public function test_crear_descuento_monto_fijo_desde_la_pantalla(): void
    {
        Livewire::actingAs($this->admin())
            ->test('discounts.index')
            ->set('name', 'Liquidación')
            ->set('type', 'fixed')
            ->set('value', '2000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('discounts', [
            'name' => 'Liquidación', 'type' => 'fixed', 'value' => 2000,
        ]);
    }

    public function test_porcentaje_no_puede_superar_cien(): void
    {
        Livewire::actingAs($this->admin())
            ->test('discounts.index')
            ->set('name', 'Imposible')
            ->set('type', 'percent')
            ->set('value', '150')
            ->call('save')
            ->assertHasErrors(['value']);
    }

    public function test_toggle_y_delete(): void
    {
        $discount = Discount::create(['name' => 'Test', 'type' => 'percent', 'value' => 10, 'active' => true]);

        $component = Livewire::actingAs($this->admin())->test('discounts.index');

        $component->call('toggle', $discount->id);
        $this->assertFalse($discount->fresh()->active);

        $component->call('delete', $discount->id);
        $this->assertModelMissing($discount);
    }

    // ---- POS aplica el descuento ----

    public function test_pos_aplica_descuento_porcentaje_al_total(): void
    {
        $admin = $this->admin();
        $this->abrirCaja($admin);
        $product = Product::create(['name' => 'Ventilador', 'price' => 1000, 'iva_rate' => 0, 'stock' => 10]);
        $discount = Discount::create(['name' => 'Día de la Madre', 'type' => 'percent', 'value' => 15, 'active' => true]);

        $pos = Livewire::actingAs($admin)->test('pos.index')
            ->call('addProduct', $product->id)
            ->set('discount_id', $discount->id);

        // $1000 - 15% = $850
        $this->assertEqualsWithDelta(850.0, $pos->instance()->total(), 0.01);

        $pos->call('addPayment')->set('payments.0.method', 'efectivo')->set('printOnSale', false)->call('cobrar')->assertHasNoErrors();

        $this->assertDatabaseHas('invoice_items', ['description' => 'Ventilador', 'discount_percent' => 15]);
    }

    public function test_pos_aplica_descuento_monto_fijo_al_total(): void
    {
        $admin = $this->admin();
        $this->abrirCaja($admin);
        $product = Product::create(['name' => 'Termo', 'price' => 1000, 'iva_rate' => 0, 'stock' => 10]);
        $discount = Discount::create(['name' => 'Liquidación', 'type' => 'fixed', 'value' => 200, 'active' => true]);

        $pos = Livewire::actingAs($admin)->test('pos.index')
            ->call('addProduct', $product->id)
            ->set('discount_id', $discount->id);

        // $1000 - $200 fijos = $800
        $this->assertEqualsWithDelta(800.0, $pos->instance()->total(), 0.01);

        $pos->call('addPayment')->set('payments.0.method', 'efectivo')->set('printOnSale', false)->call('cobrar')->assertHasNoErrors();

        $this->assertDatabaseHas('invoice_items', ['description' => 'Termo', 'discount_percent' => 20]);
    }

    public function test_pos_no_aplica_descuento_inactivo_aunque_el_id_llegue_seteado(): void
    {
        $admin = $this->admin();
        $product = Product::create(['name' => 'Plancha', 'price' => 1000, 'iva_rate' => 0, 'stock' => 10]);
        $discount = Discount::create(['name' => 'Vencido', 'type' => 'percent', 'value' => 50, 'active' => false]);

        $pos = Livewire::actingAs($admin)->test('pos.index')
            ->call('addProduct', $product->id)
            ->set('discount_id', $discount->id);

        $this->assertEqualsWithDelta(1000.0, $pos->instance()->total(), 0.01);
    }

    public function test_pos_no_aplica_descuento_fuera_de_vigencia(): void
    {
        $admin = $this->admin();
        $product = Product::create(['name' => 'Licuadora', 'price' => 1000, 'iva_rate' => 0, 'stock' => 10]);
        $discount = Discount::create([
            'name' => 'Ya pasó', 'type' => 'percent', 'value' => 50, 'active' => true,
            'starts_at' => now()->subDays(10)->toDateString(), 'ends_at' => now()->subDays(1)->toDateString(),
        ]);

        $pos = Livewire::actingAs($admin)->test('pos.index')
            ->call('addProduct', $product->id)
            ->set('discount_id', $discount->id);

        $this->assertEqualsWithDelta(1000.0, $pos->instance()->total(), 0.01);
    }

    public function test_pos_combina_descuento_manual_con_promo_de_producto(): void
    {
        $admin = $this->admin();
        $product = Product::create(['name' => 'Pava', 'price' => 1000, 'iva_rate' => 0, 'stock' => 10]);
        Promotion::create(['product_id' => $product->id, 'type' => 'segunda', 'percent' => 50, 'active' => true]);
        $discount = Discount::create(['name' => 'Día de la Madre', 'type' => 'percent', 'value' => 10, 'active' => true]);

        $pos = Livewire::actingAs($admin)->test('pos.index')
            ->call('addProduct', $product->id)
            ->set('cart.0.quantity', 2)
            ->set('discount_id', $discount->id);

        // 2 unidades, 2da al 50% -> $1500 antes del descuento manual.
        // Con 10% manual compuesto: 1500 * 0.9 = 1350.
        $this->assertEqualsWithDelta(1350.0, $pos->instance()->total(), 0.01);
    }

    /**
     * Reproduce el bug reportado: "Mis Descuentos" (descuento manual global)
     * y el descuento clasico por medio de pago (efectivo/transferencia) se
     * estaban acumulando - con un descuento manual activo, pagar en efectivo
     * restaba OTRO % encima del total ya descontado.
     */
    public function test_mis_descuentos_no_se_acumula_con_el_descuento_por_medio_de_pago(): void
    {
        $admin = $this->admin();
        $this->abrirCaja($admin);
        CompanySettings::current()->update(['descuento_efectivo_pct' => 15]);
        $product = Product::create(['name' => 'Ventilador', 'price' => 1000, 'iva_rate' => 0, 'stock' => 10]);
        $discount = Discount::create(['name' => 'Día de la Madre', 'type' => 'percent', 'value' => 15, 'active' => true]);

        $pos = Livewire::actingAs($admin)->test('pos.index')
            ->call('addProduct', $product->id)
            ->set('discount_id', $discount->id)
            ->call('addPayment')
            ->set('payments.0.method', 'efectivo');

        // Mis Descuentos ya aplico 15% ($1000 -> $850, ver total()). El pago
        // en efectivo NO deberia restar otro 15% encima de eso.
        $this->assertEqualsWithDelta(850.0, $pos->instance()->total(), 0.01);
        $this->assertSame(0.0, $pos->instance()->paymentDiscountPct($pos->get('payments.0')));
        $this->assertEqualsWithDelta(850.0, $pos->instance()->montoRealPago($pos->get('payments.0')), 0.01);

        $pos->set('printOnSale', false)->call('cobrar')->assertHasNoErrors();

        $invoice = Invoice::latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(850.0, (float) $invoice->total, 0.01);
        $this->assertDatabaseHas('invoice_payments', ['method' => 'efectivo', 'amount' => 850]);
    }

    public function test_solo_muestra_descuentos_vigentes_en_el_selector_del_pos(): void
    {
        Discount::create(['name' => 'Vigente', 'type' => 'percent', 'value' => 10, 'active' => true]);
        Discount::create(['name' => 'Apagado', 'type' => 'percent', 'value' => 10, 'active' => false]);

        Livewire::actingAs($this->admin())->test('pos.index')
            ->assertSee('Vigente')
            ->assertDontSee('Apagado');
    }

    /**
     * Reproduce el bug reportado: se carga un pago en efectivo que cubre el
     * 100% del total CON "Mis Descuentos" activo, y después se saca el
     * descuento. El monto del pago se reescala para seguir cubriendo el
     * 100% del nuevo total (sin esto, quedaba un saldo a cuenta corriente
     * no pedido por el cajero).
     */
    public function test_al_sacar_mis_descuentos_reescala_el_pago_que_cubria_el_total(): void
    {
        $admin = $this->admin();
        $this->abrirCaja($admin);
        $product = Product::create(['name' => 'Cafetero', 'price' => 26800, 'iva_rate' => 0, 'stock' => 10]);
        $discount = Discount::create(['name' => 'Promo', 'type' => 'percent', 'value' => 25, 'active' => true]);

        $pos = Livewire::actingAs($admin)->test('pos.index')
            ->call('addProduct', $product->id)
            ->set('discount_id', $discount->id)
            ->call('addPayment')
            ->set('payments.0.method', 'efectivo');

        // $26800 - 25% = $20100, pago prellenado cubre el 100%.
        $this->assertEqualsWithDelta(20100.0, $pos->instance()->total(), 0.01);
        $this->assertEqualsWithDelta(20100.0, (float) $pos->get('payments.0.amount'), 0.01);

        $pos->set('discount_id', null);

        // Se sacó el descuento: el total vuelve a $26800 y el pago se
        // reescala solo para seguir cubriendo el 100%, sin saldo a cta cte.
        $this->assertEqualsWithDelta(26800.0, $pos->instance()->total(), 0.01);
        $this->assertEqualsWithDelta(26800.0, (float) $pos->get('payments.0.amount'), 0.01);
        $this->assertEqualsWithDelta(0.0, $pos->instance()->saldoPendiente(), 0.01);
    }

    /** Si el pago era parcial a propósito (deja saldo en cta cte), no se toca al cambiar el descuento. */
    public function test_pago_parcial_no_se_reescala_al_cambiar_mis_descuentos(): void
    {
        $admin = $this->admin();
        $this->abrirCaja($admin);
        $product = Product::create(['name' => 'Cafetero', 'price' => 26800, 'iva_rate' => 0, 'stock' => 10]);
        $discount = Discount::create(['name' => 'Promo', 'type' => 'percent', 'value' => 25, 'active' => true]);

        $pos = Livewire::actingAs($admin)->test('pos.index')
            ->call('addProduct', $product->id)
            ->set('discount_id', $discount->id)
            ->call('addPayment')
            ->set('payments.0.method', 'efectivo')
            ->set('payments.0.amount', '10000');

        $pos->set('discount_id', null);

        // Pago parcial a propósito: se deja intacto, el saldo a cta cte
        // simplemente refleja el nuevo total sin descuento.
        $this->assertEqualsWithDelta(10000.0, (float) $pos->get('payments.0.amount'), 0.01);
        $this->assertEqualsWithDelta(16800.0, $pos->instance()->saldoPendiente(), 0.01);
    }
}
