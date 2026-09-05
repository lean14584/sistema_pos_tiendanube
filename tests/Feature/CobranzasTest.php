<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Whatsapp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CobranzasTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    private function facturaImpaga(Client $client, float $total): Invoice
    {
        $invoice = Invoice::create([
            'number' => 'FAC-'.uniqid(),
            'client_id' => $client->id,
            'tax_rate' => 0,
            'issue_date' => now(),
            'due_date' => now(),
            'status' => 'pending',
        ]);
        $invoice->items()->create([
            'description' => 'Item', 'quantity' => 1, 'unit_price' => $total, 'iva_rate' => 0,
        ]);

        return $invoice;
    }

    public function test_whatsapp_normaliza_el_numero_y_arma_el_link(): void
    {
        $this->assertNull(Whatsapp::link(''));
        $this->assertNull(Whatsapp::link('sin numeros'));
        // Sin código de país: se le antepone 54.
        $this->assertSame('https://wa.me/5493511234567', Whatsapp::link('93511234567'));
        // Con código de país: se respeta tal cual.
        $this->assertSame('https://wa.me/5493511234567', Whatsapp::link('5493511234567'));
        // El mensaje viaja como querystring.
        $this->assertStringContainsString('?text=', Whatsapp::link('5493511234567', 'Hola'));
    }

    public function test_lista_solo_los_clientes_con_saldo_pendiente(): void
    {
        $deudor = Client::create(['name' => 'Deudor', 'email' => 'd@test.com', 'phone' => '3511234567']);
        $alDia = Client::create(['name' => 'Al Dia', 'email' => 'a@test.com']);

        $this->facturaImpaga($deudor, 5000);
        // Al Dia tiene factura pero pagada al momento (no queda saldo).
        $inv = $this->facturaImpaga($alDia, 2000);
        $inv->payments()->create(['method' => 'efectivo', 'amount' => 2000, 'date' => now()]);

        Livewire::actingAs($this->admin())
            ->test('cobranzas.index')
            ->assertSee('Deudor')
            ->assertDontSee('Al Dia')
            // Formato completo (no solo "5.000"), para no pasar "de casualidad"
            // contra el link de WhatsApp si algún día ese texto cambia.
            ->assertSee('5.000,00');
    }

    public function test_cobrar_desde_cobranzas_registra_el_pago_y_baja_el_saldo(): void
    {
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);

        $deudor = Client::create(['name' => 'Deudor', 'email' => 'd@test.com', 'phone' => '3511234567']);
        $this->facturaImpaga($deudor, 5000);

        Livewire::actingAs($admin)
            ->test('cobranzas.index')
            ->call('startPayment', $deudor->id, 5000)
            ->set('payAmount', '3000')
            ->call('savePayment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('client_payments', ['client_id' => $deudor->id, 'amount' => 3000]);
        $this->assertDatabaseHas('cash_movements', ['type' => 'ingreso', 'amount' => 3000]);
    }
}
