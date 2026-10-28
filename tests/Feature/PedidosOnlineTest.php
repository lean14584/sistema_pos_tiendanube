<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Client;
use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Models\Sucursal;
use App\Models\TiendaPedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El Remito pendiente de un pedido online vive acá, no en Facturas (ver
 * feedback del usuario: mezclado con las ventas del local confundía). Una
 * vez convertido con "Entregar y cobrar", la Factura resultante pasa a
 * Facturas como cualquier venta — ver InvoicesTest para esa mitad.
 */
class PedidosOnlineTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    private function crearPedidoConRemito(string $token, string $status = 'pending_payment', string $customerName = 'Rocío Fernández'): TiendaPedido
    {
        $client = Client::create(['name' => 'Consumidor Final', 'email' => 'cf@test.com']);
        $pedido = TiendaPedido::create([
            'token' => $token, 'sucursal_id' => Sucursal::sole()->id, 'status' => $status,
            'customer_name' => $customerName, 'customer_email' => strtolower(str_replace(' ', '.', $customerName)).'@example.com',
            'cart_snapshot' => [], 'expires_at' => now()->addMinutes(30),
        ]);
        $remito = Invoice::create([
            'number' => 'REM-'.$token, 'client_id' => $client->id, 'tax_rate' => 0,
            'tipo_comprobante_interno' => 'remito_x',
            'issue_date' => now(), 'due_date' => now(), 'status' => $status === 'paid' ? 'paid' : 'pending',
            'tienda_pedido_id' => $pedido->id,
        ]);
        $remito->items()->create(['description' => 'Item', 'quantity' => 1, 'unit_price' => 1000, 'iva_rate' => 21]);
        $pedido->update(['invoice_id' => $remito->id]);

        return $pedido->fresh();
    }

    public function test_pedidos_online_lista_el_remito_pendiente(): void
    {
        $pedido = $this->crearPedidoConRemito('tok-list-1');

        Livewire::actingAs($this->admin())->test('pedidos-online.index')
            ->assertSee('Rocío Fernández')
            ->assertSee('Pendiente de pago')
            ->assertSee('Ver →');
    }

    public function test_el_remito_pendiente_de_un_pedido_online_no_aparece_en_facturas(): void
    {
        $pedido = $this->crearPedidoConRemito('tok-list-2');

        Livewire::actingAs($this->admin())->test('invoices.index')
            ->assertDontSee($pedido->invoice->number);
    }

    public function test_la_factura_generada_al_entregar_si_aparece_en_facturas(): void
    {
        CompanySettings::current()->update(['tienda_online_habilitada' => true]);
        $pedido = $this->crearPedidoConRemito('tok-list-3');

        $admin = $this->admin();
        \App\Models\CashSession::create([
            'user_id' => $admin->id, 'sucursal_id' => Sucursal::sole()->id,
            'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0,
        ]);

        Livewire::actingAs($admin)->test('invoices.show', ['invoice' => $pedido->invoice])
            ->call('openEntregaModal')
            ->call('entregarYCobrar');

        $remitoNumero = $pedido->invoice->fresh()->number;
        $factura = $pedido->invoice->fresh()->facturaGenerada();

        Livewire::actingAs($admin)->test('invoices.index')
            ->assertSee($factura->number)
            ->assertDontSee($remitoNumero);
    }

    public function test_filtro_de_retiro_en_pedidos_online(): void
    {
        $this->crearPedidoConRemito('tok-list-4', 'paid', 'Sin Retirar');
        $retirado = $this->crearPedidoConRemito('tok-list-5', 'paid', 'Ya Retirado');
        $retirado->update(['picked_up_at' => now()]);

        Livewire::actingAs($this->admin())->test('pedidos-online.index')
            ->set('filterRetiro', 'retirado')
            ->assertSee('Ya Retirado')
            ->assertDontSee('Sin Retirar');
    }
}
