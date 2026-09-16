<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Purchase;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\CurrentSucursal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Mismo hueco que tenía Invoices antes de InvoiceSucursalScopeTest: el
 * listado de compras no filtraba por sucursal (un encargado veía las de
 * TODA la empresa) y el acceso directo a una compra puntual por URL no
 * chequeaba nada.
 */
class PurchaseSucursalScopeTest extends TestCase
{
    use RefreshDatabase;

    private function sucursal(string $name, int $puntoVenta): Sucursal
    {
        return Sucursal::create(['name' => $name, 'razon_social' => 'Mi Empresa SRL', 'punto_venta' => $puntoVenta, 'active' => true]);
    }

    private function purchaseEn(Sucursal $sucursal, string $number = 'COM-0001'): Purchase
    {
        $provider = Provider::create(['name' => 'Proveedor '.uniqid()]);

        return Purchase::create([
            'number' => $number,
            'provider_id' => $provider->id,
            'sucursal_id' => $sucursal->id,
            'issue_date' => now(),
            'due_date' => now()->addDays(15),
            'tax_rate' => 0,
            'status' => 'draft',
        ]);
    }

    public function test_el_listado_solo_muestra_las_compras_de_la_propia_sucursal(): void
    {
        $centro = $this->sucursal('Centro', 81);
        $norte = $this->sucursal('Norte', 82);

        $this->purchaseEn($centro, 'COM-0001');
        $this->purchaseEn($norte, 'COM-0002');

        $encargado = User::factory()->create(['role' => Role::Encargado, 'active' => true, 'sucursal_id' => $centro->id]);

        $component = Livewire::actingAs($encargado)->test('purchases.index');

        $this->assertCount(1, $component->viewData('purchases'));
    }

    public function test_encargado_no_puede_ver_una_compra_de_otra_sucursal(): void
    {
        $otra = $this->sucursal('Otra', 83);
        $mia = $this->sucursal('Mia', 84);
        $purchase = $this->purchaseEn($otra);

        $encargado = User::factory()->create(['role' => Role::Encargado, 'active' => true, 'sucursal_id' => $mia->id]);

        $this->actingAs($encargado)->get(route('purchases.show', $purchase))->assertForbidden();
        $this->actingAs($encargado)->get(route('purchases.edit', $purchase))->assertForbidden();
    }

    public function test_admin_si_puede_ver_compras_de_cualquier_sucursal(): void
    {
        $otra = $this->sucursal('Otra', 85);
        $purchase = $this->purchaseEn($otra);

        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);

        $this->actingAs($admin)->get(route('purchases.show', $purchase))->assertOk();
        $this->actingAs($admin)->get(route('purchases.edit', $purchase))->assertOk();
    }

    /**
     * MEJORA: CashLinker::linkPurchasePayment() no recibía $sucursalId — al
     * editar una compra de OTRA sucursal (distinta de la activa), el pago se
     * intentaba anotar en la caja de la sucursal activa (donde el admin no
     * tenía sesión abierta) en vez de en la caja de la sucursal DE LA
     * COMPRA, y el movimiento de caja quedaba invisible para el arqueo. El
     * chequeo previo (hasOpenSession) ya usaba el sucursalId correcto, así
     * que la validación pasaba pero el link fallaba en silencio.
     */
    public function test_editar_compra_de_otra_sucursal_anota_el_pago_en_la_caja_de_la_compra(): void
    {
        $centro = $this->sucursal('Centro', 86);
        $norte = $this->sucursal('Norte', 87);
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);

        $purchase = $this->purchaseEn($centro);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 0]);
        $purchase->items()->create(['product_id' => $product->id, 'description' => 'Notebook', 'quantity' => 1, 'unit_price' => 1000]);

        // El admin tiene su caja abierta en CENTRO (la sucursal de la
        // compra), pero está parado con la sucursal activa en NORTE.
        CashSession::create(['user_id' => $admin->id, 'sucursal_id' => $centro->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);
        CurrentSucursal::set($norte->id);

        Livewire::actingAs($admin)
            ->test('purchases.edit', ['purchase' => $purchase])
            ->call('addPayment')
            ->set('payments.0.amount', '1000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, $purchase->fresh()->payments()->count());
        $this->assertSame(1, CashMovement::count(), 'El pago debería quedar anotado en la caja de Centro (la de la compra), no perderse.');
    }
}
