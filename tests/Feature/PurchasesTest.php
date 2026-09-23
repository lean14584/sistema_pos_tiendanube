<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\SucursalSwitcher;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Provider;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\CashLinker;
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

    /**
     * MEJORA: este formulario de alta no tenía ningún estado para detectar
     * "esto ya se guardó" — el lock de 'purchase-number' solo serializaba la
     * NUMERACIÓN, así que dos submits casi simultáneos (doble clic) creaban
     * dos compras con números distintos, sumando el stock dos veces. Mismo
     * patrón de test que InvoicesTest::test_doble_clic_en_guardar_no_duplica_la_factura.
     */
    public function test_doble_clic_en_guardar_no_duplica_la_compra(): void
    {
        $provider = Provider::create(['name' => 'Proveedor 1']);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 5]);

        $component = Livewire::actingAs($this->admin())
            ->test('purchases.create')
            ->set('provider_id', (string) $provider->id)
            ->call('addProductItem', $product->id)
            ->set('items.0.quantity', '3');

        $component->call('save')->assertHasNoErrors();
        $component->call('save')->assertHasNoErrors(); // el flag corta antes de crear nada, no un error de validación

        $this->assertSame(1, Purchase::count(), 'No debería duplicarse la compra al guardar dos veces la misma pantalla.');
        $this->assertEquals(8, $product->fresh()->stock, 'El stock no debería sumarse dos veces.');
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

    /**
     * MEJORA: este save() de edición no tenía ninguna protección contra
     * doble-submit (a diferencia de purchases.create, ver el test de acá
     * arriba) - dos submits casi simultáneos revertían/reaplicaban el
     * stock y desvinculaban/vinculaban pagos por su cuenta cada uno.
     */
    public function test_doble_clic_en_guardar_edicion_no_duplica_el_ajuste_de_stock(): void
    {
        $provider = Provider::create(['name' => 'Proveedor 1']);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 5]);

        $purchase = Purchase::create([
            'number' => 'COM-0001', 'provider_id' => $provider->id, 'tax_rate' => 0,
            'issue_date' => now(), 'due_date' => now()->addDays(15), 'status' => 'draft',
        ]);
        $purchase->items()->create(['product_id' => $product->id, 'description' => 'Notebook', 'quantity' => 3, 'unit_price' => 1000]);
        $product->increment('stock', 3);

        $component = Livewire::actingAs($this->admin())
            ->test('purchases.edit', ['purchase' => $purchase])
            ->set('items.0.quantity', '5');

        $component->call('save')->assertHasNoErrors();
        $component->call('save')->assertHasNoErrors(); // el flag corta antes de tocar nada, no un error de validación

        // Old +3 reverted, new +5 applied ONCE: 8 - 3 + 5 = 10 (no 12 ni 15)
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

    /**
     * MEJORA: Purchases\Edit/Show revertían y reaplicaban el stock en la
     * sucursal ACTIVA de quien editaba/borraba, no en la sucursal real
     * donde se cargó la compra (purchases no guardaba sucursal_id). Un
     * admin que cambia de sucursal activa entre medio terminaba moviendo
     * stock en el local equivocado.
     */
    public function test_borrar_una_compra_revierte_el_stock_en_su_propia_sucursal_no_en_la_activa(): void
    {
        $principal = Sucursal::sole();
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);
        $provider = Provider::create(['name' => 'Proveedor 1']);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 5]);

        $admin = $this->admin();

        // La compra se carga con Principal como sucursal activa (default).
        Livewire::actingAs($admin)
            ->test('purchases.create')
            ->set('provider_id', (string) $provider->id)
            ->call('addProductItem', $product->id)
            ->set('items.0.quantity', '3')
            ->call('save');

        $purchase = Purchase::sole();
        $this->assertEquals($principal->id, $purchase->sucursal_id);
        $this->assertEquals(3, $product->stockEnSucursal($principal->id));

        // El admin cambia de sucursal activa a Norte y recién ahí borra la compra.
        Livewire::actingAs($admin)->test(SucursalSwitcher::class)->set('sucursalId', (string) $norte->id);

        Livewire::actingAs($admin)
            ->test('purchases.show', ['purchase' => $purchase->fresh()])
            ->call('delete');

        $this->assertEquals(0, $product->stockEnSucursal($principal->id), 'El stock revertido tiene que salir de Principal (donde se cargó), no de Norte.');
        $this->assertEquals(0, $product->stockEnSucursal($norte->id), 'Norte nunca tuvo stock de este producto, no debería quedar en negativo ni tocado.');
        $this->assertEquals(5, $product->fresh()->stock);
    }

    public function test_editar_una_compra_ajusta_el_stock_en_su_propia_sucursal_no_en_la_activa(): void
    {
        $principal = Sucursal::sole();
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);
        $provider = Provider::create(['name' => 'Proveedor 1']);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 5]);

        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test('purchases.create')
            ->set('provider_id', (string) $provider->id)
            ->call('addProductItem', $product->id)
            ->set('items.0.quantity', '3')
            ->call('save');

        $purchase = Purchase::sole();
        $this->assertEquals(3, $product->stockEnSucursal($principal->id));

        Livewire::actingAs($admin)->test(SucursalSwitcher::class)->set('sucursalId', (string) $norte->id);

        Livewire::actingAs($admin)
            ->test('purchases.edit', ['purchase' => $purchase])
            ->set('items.0.quantity', '5')
            ->call('save');

        $this->assertEquals(5, $product->stockEnSucursal($principal->id), 'El delta (+2) tiene que aplicarse en Principal, donde se cargó la compra.');
        $this->assertEquals(0, $product->stockEnSucursal($norte->id));
    }

    /**
     * MEJORA: editar la cantidad de un ítem que ya tiene un lote cargado
     * (Purchases\Create con vencimiento) dejaba el lote desincronizado —
     * esta pantalla ni siquiera muestra el campo de lote para corregirlo.
     * Ahora se bloquea con un mensaje claro en vez de corromper el dato.
     */
    public function test_no_se_puede_cambiar_la_cantidad_de_un_item_con_lote_al_editar_una_compra(): void
    {
        $provider = Provider::create(['name' => 'Proveedor 1']);
        $product = Product::create(['name' => 'Yogur', 'price' => 500, 'stock' => 0]);

        Livewire::actingAs($this->admin())
            ->test('purchases.create')
            ->set('provider_id', (string) $provider->id)
            ->call('addProductItem', $product->id)
            ->set('items.0.quantity', '10')
            ->set('items.0.expiration_date', now()->addDays(30)->toDateString())
            ->call('save');

        $purchase = Purchase::sole();
        $this->assertEquals(1, ProductBatch::where('purchase_id', $purchase->id)->count());

        Livewire::actingAs($this->admin())
            ->test('purchases.edit', ['purchase' => $purchase])
            ->set('items.0.quantity', '20')
            ->call('save')
            ->assertHasErrors('items');

        $this->assertEquals(10, ProductBatch::where('purchase_id', $purchase->id)->value('quantity_remaining'), 'El lote no debería tocarse si la cantidad se bloqueó.');
        $this->assertEquals(10, $product->fresh()->stock, 'El stock tampoco debería cambiar si el guardado se rechazó.');
    }

    /**
     * MEJORA: borrar una compra revertía el stock pero dejaba sus
     * ProductBatch vivos (purchase_id -> null por el nullOnDelete), con
     * quantity_remaining intacta — quedaban lotes "fantasma" representando
     * stock que ya no existía.
     */
    public function test_borrar_una_compra_borra_tambien_sus_lotes(): void
    {
        $provider = Provider::create(['name' => 'Proveedor 1']);
        $product = Product::create(['name' => 'Yogur', 'price' => 500, 'stock' => 0]);

        Livewire::actingAs($this->admin())
            ->test('purchases.create')
            ->set('provider_id', (string) $provider->id)
            ->call('addProductItem', $product->id)
            ->set('items.0.quantity', '10')
            ->set('items.0.expiration_date', now()->addDays(30)->toDateString())
            ->call('save');

        $purchase = Purchase::sole();
        $this->assertEquals(1, ProductBatch::count());

        Livewire::actingAs($this->admin())
            ->test('purchases.show', ['purchase' => $purchase])
            ->call('delete');

        $this->assertEquals(0, ProductBatch::count());
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

    public function test_no_puede_registrar_un_pago_al_crear_compra_sin_caja_abierta(): void
    {
        $provider = Provider::create(['name' => 'Proveedor 1']);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 5]);

        Livewire::actingAs($this->admin())
            ->test('purchases.create')
            ->set('provider_id', (string) $provider->id)
            ->call('addProductItem', $product->id)
            ->set('items.0.quantity', '1')
            ->set('items.0.unit_price', '1000')
            ->call('addPayment')
            ->set('payments.0.amount', '1000')
            ->call('save')
            ->assertHasErrors('payments');

        $this->assertSame(0, Purchase::count());
        $this->assertSame(5, $product->fresh()->stock);
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
        $this->actingAs($admin);
        CashLinker::linkPurchasePayment($purchase, $oldPayment);

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

    public function test_no_puede_agregar_un_pago_al_editar_compra_sin_caja_abierta(): void
    {
        $provider = Provider::create(['name' => 'Proveedor 1']);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 5]);

        $purchase = Purchase::create([
            'number' => 'COM-0001', 'provider_id' => $provider->id, 'tax_rate' => 0,
            'issue_date' => now(), 'due_date' => now()->addDays(15), 'status' => 'draft',
        ]);
        $purchase->items()->create(['product_id' => $product->id, 'description' => 'Notebook', 'quantity' => 1, 'unit_price' => 1000]);

        Livewire::actingAs($this->admin())
            ->test('purchases.edit', ['purchase' => $purchase])
            ->call('addPayment')
            ->set('payments.0.amount', '1000')
            ->call('save')
            ->assertHasErrors('payments');

        $this->assertSame(0, $purchase->fresh()->payments->count());
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
        $this->actingAs($admin);
        CashLinker::linkPurchasePayment($purchase, $payment);

        $this->assertSame(1, CashMovement::count());

        Livewire::actingAs($admin)
            ->test('purchases.show', ['purchase' => $purchase])
            ->call('delete');

        $this->assertSame(0, CashMovement::count());
    }

    public function test_crear_compra_sin_detalle_no_requiere_productos_ni_mueve_stock(): void
    {
        $provider = Provider::create(['name' => 'Proveedor 1']);

        Livewire::actingAs($this->admin())
            ->test('purchases.create')
            ->set('provider_id', (string) $provider->id)
            ->set('sin_detalle', true)
            ->set('manual_total', '5000')
            ->set('remito_number', '0001-00001234')
            ->call('save')
            ->assertHasNoErrors();

        $purchase = Purchase::sole();
        $this->assertTrue($purchase->sin_detalle);
        $this->assertEqualsWithDelta(5000.0, (float) $purchase->total, 0.01);
        $this->assertSame('0001-00001234', $purchase->remito_number);
        $this->assertCount(0, $purchase->items);
    }

    public function test_crear_compra_sin_detalle_sin_total_da_error(): void
    {
        $provider = Provider::create(['name' => 'Proveedor 1']);

        Livewire::actingAs($this->admin())
            ->test('purchases.create')
            ->set('provider_id', (string) $provider->id)
            ->set('sin_detalle', true)
            ->call('save')
            ->assertHasErrors('manual_total');

        $this->assertSame(0, Purchase::count());
    }

    public function test_crear_compra_sin_marcar_sin_detalle_y_sin_productos_da_error(): void
    {
        $provider = Provider::create(['name' => 'Proveedor 1']);

        Livewire::actingAs($this->admin())
            ->test('purchases.create')
            ->set('provider_id', (string) $provider->id)
            ->call('save')
            ->assertHasErrors('items');

        $this->assertSame(0, Purchase::count());
    }

    public function test_activar_sin_detalle_en_el_form_descarta_los_productos_ya_cargados(): void
    {
        $provider = Provider::create(['name' => 'Proveedor 1']);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 5]);

        $component = Livewire::actingAs($this->admin())
            ->test('purchases.create')
            ->set('provider_id', (string) $provider->id)
            ->call('addProductItem', $product->id);

        $this->assertCount(1, $component->get('items'));

        $component->set('sin_detalle', true);

        $this->assertCount(0, $component->get('items'), 'Al activar "sin detalle" los productos ya cargados deberían descartarse.');
    }

    public function test_editar_compra_con_productos_para_marcarla_sin_detalle_revierte_el_stock(): void
    {
        $provider = Provider::create(['name' => 'Proveedor 1']);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 5]);

        $purchase = Purchase::create([
            'number' => 'COM-0001', 'provider_id' => $provider->id, 'tax_rate' => 0,
            'issue_date' => now(), 'due_date' => now()->addDays(15), 'status' => 'draft',
        ]);
        $purchase->items()->create(['product_id' => $product->id, 'description' => 'Notebook', 'quantity' => 3, 'unit_price' => 1000]);
        $product->increment('stock', 3);

        Livewire::actingAs($this->admin())
            ->test('purchases.edit', ['purchase' => $purchase])
            ->set('sin_detalle', true)
            ->set('manual_total', '2000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(5, $product->fresh()->stock, 'El stock de los ítems viejos tiene que revertirse al pasar la compra a sin detalle.');
        $this->assertTrue($purchase->fresh()->sin_detalle);
        $this->assertCount(0, $purchase->fresh()->items);
        $this->assertEqualsWithDelta(2000.0, (float) $purchase->fresh()->total, 0.01);
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
