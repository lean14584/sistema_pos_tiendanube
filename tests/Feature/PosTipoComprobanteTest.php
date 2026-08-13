<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TipoComprobanteInterno;
use App\Models\CashSession;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PosTipoComprobanteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_el_pos_respeta_el_tipo_de_comprobante_elegido(): void
    {
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);

        $product = Product::create(['name' => 'Agua', 'price' => 500, 'iva_rate' => 0, 'stock' => 10]);

        Livewire::actingAs($admin)->test('pos.index')
            ->call('addProduct', $product->id)
            ->set('tipo_comprobante_interno', TipoComprobanteInterno::RemitoX->value)
            ->call('addPayment')
            ->set('printOnSale', false)
            ->call('cobrar')
            ->assertHasNoErrors();

        $invoice = Invoice::latest('id')->first();
        $this->assertSame(TipoComprobanteInterno::RemitoX, $invoice->tipo_comprobante_interno);
    }

    public function test_por_defecto_usa_el_tipo_predeterminado_de_la_empresa(): void
    {
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);

        $product = Product::create(['name' => 'Agua', 'price' => 500, 'iva_rate' => 0, 'stock' => 10]);

        // Sin tocar el tipo: cae al predeterminado (Factura B con la config por defecto).
        Livewire::actingAs($admin)->test('pos.index')
            ->call('addProduct', $product->id)
            ->call('addPayment')
            ->set('printOnSale', false)
            ->call('cobrar')
            ->assertHasNoErrors();

        $invoice = Invoice::latest('id')->first();
        $this->assertSame(TipoComprobanteInterno::FacturaB, $invoice->tipo_comprobante_interno);
    }
}
