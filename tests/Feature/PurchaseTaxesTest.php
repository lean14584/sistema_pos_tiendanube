<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PurchaseTaxesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_las_percepciones_se_guardan_y_suman_al_total(): void
    {
        $provider = Provider::create(['name' => 'Proveedor 1']);
        $product = Product::create(['name' => 'Mercadería', 'price' => 1000, 'stock' => 0]);

        Livewire::actingAs($this->admin())
            ->test('purchases.create')
            ->set('provider_id', (string) $provider->id)
            ->call('addProductItem', $product->id) // 1 x 1000
            ->set('tax_rate', '21')                // IVA 210
            ->call('addTax')
            ->set('taxes.0.concepto', 'Percepción IIBB')
            ->set('taxes.0.amount', '250')
            ->call('addTax')
            ->set('taxes.1.concepto', 'Percepción IVA')
            ->set('taxes.1.amount', '30')
            ->call('save')
            ->assertHasNoErrors();

        $purchase = Purchase::with('taxes')->first();
        $this->assertNotNull($purchase);
        $this->assertCount(2, $purchase->taxes);
        $this->assertDatabaseHas('purchase_taxes', ['concepto' => 'Percepción IIBB', 'amount' => 250]);

        // Total = subtotal 1000 + IVA 210 + percepciones 280 = 1490
        $this->assertEqualsWithDelta(1490.0, (float) $purchase->total, 0.01);
        $this->assertEqualsWithDelta(280.0, (float) $purchase->percepciones_total, 0.01);
    }

    public function test_las_filas_de_impuesto_vacias_o_sin_monto_se_ignoran(): void
    {
        $provider = Provider::create(['name' => 'Proveedor 1']);
        $product = Product::create(['name' => 'Mercadería', 'price' => 500, 'stock' => 0]);

        Livewire::actingAs($this->admin())
            ->test('purchases.create')
            ->set('provider_id', (string) $provider->id)
            ->call('addProductItem', $product->id)
            ->call('addTax') // fila vacía, no se debe guardar
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('purchase_taxes', 0);
    }

    public function test_editar_una_compra_reemplaza_sus_percepciones(): void
    {
        $provider = Provider::create(['name' => 'Proveedor 1']);
        $product = Product::create(['name' => 'Mercadería', 'price' => 1000, 'stock' => 0]);

        $purchase = Purchase::create([
            'number' => 'COM-9001', 'provider_id' => $provider->id, 'tax_rate' => 0,
            'issue_date' => now(), 'due_date' => now()->addDays(15), 'status' => 'draft',
        ]);
        $purchase->items()->create(['product_id' => $product->id, 'description' => 'Mercadería', 'quantity' => 1, 'unit_price' => 1000]);
        $purchase->taxes()->create(['concepto' => 'Percepción vieja', 'amount' => 100]);

        Livewire::actingAs($this->admin())
            ->test('purchases.edit', ['purchase' => $purchase])
            ->set('taxes.0.concepto', 'Percepción IIBB')
            ->set('taxes.0.amount', '150')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('purchase_taxes', ['concepto' => 'Percepción vieja']);
        $this->assertDatabaseHas('purchase_taxes', ['concepto' => 'Percepción IIBB', 'amount' => 150]);
        $this->assertDatabaseCount('purchase_taxes', 1);
    }
}
