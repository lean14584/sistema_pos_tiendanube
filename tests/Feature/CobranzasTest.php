<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Invoice;
use App\Models\Sucursal;
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

    private function facturaImpaga(Client $client, float $total, ?int $sucursalId = null): Invoice
    {
        $invoice = Invoice::create([
            'number' => 'FAC-'.uniqid(),
            'client_id' => $client->id,
            'sucursal_id' => $sucursalId,
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
        CashSession::create(['user_id' => $admin->id, 'sucursal_id' => Sucursal::sole()->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);

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

    /**
     * MEJORA: savePayment() no tenía ningún lock — dos submits casi
     * simultáneos (doble clic) creaban dos ClientPayment del mismo cobro.
     * cancelPayment() ya vacía payingClientId/payAmount al final de un cobro
     * exitoso, así que una segunda llamada sobre la misma instancia
     * encuentra el form vacío y falla la validación (mismo patrón de test
     * que RemitoFacturarTest).
     */
    public function test_doble_clic_en_registrar_cobro_no_lo_duplica(): void
    {
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'sucursal_id' => Sucursal::sole()->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);

        $deudor = Client::create(['name' => 'Deudor', 'email' => 'd@test.com', 'phone' => '3511234567']);
        $this->facturaImpaga($deudor, 5000);

        $component = Livewire::actingAs($admin)
            ->test('cobranzas.index')
            ->call('startPayment', $deudor->id, 5000)
            ->set('payAmount', '3000');

        $component->call('savePayment')->assertHasNoErrors();
        $component->call('savePayment')->assertHasErrors('payingClientId');

        $this->assertSame(1, ClientPayment::count(), 'No debería duplicarse el cobro al registrarlo dos veces.');
        $this->assertDatabaseHas('client_payments', ['client_id' => $deudor->id, 'amount' => 3000]);
    }

    /**
     * MEJORA: deudores() no filtraba por sucursal (mismo criterio que
     * Reports/Vencimientos/Invoices/Purchases/Audit/ProductBatches) — un
     * cajero/vendedor de una sucursal veía y podía cobrar la deuda de
     * facturas de TODA la cadena, no solo la suya.
     */
    public function test_un_vendedor_solo_ve_deudores_de_su_propia_sucursal(): void
    {
        $centro = Sucursal::create(['name' => 'Centro', 'razon_social' => 'Mi Empresa', 'punto_venta' => 88]);
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa', 'punto_venta' => 89]);
        $vendedor = User::factory()->create(['role' => Role::Vendedor, 'active' => true, 'sucursal_id' => $centro->id]);

        $deudorCentro = Client::create(['name' => 'Deudor Centro', 'email' => 'dc@test.com']);
        $deudorNorte = Client::create(['name' => 'Deudor Norte', 'email' => 'dn@test.com']);
        $this->facturaImpaga($deudorCentro, 5000, $centro->id);
        $this->facturaImpaga($deudorNorte, 7000, $norte->id);

        Livewire::actingAs($vendedor)
            ->test('cobranzas.index')
            ->assertSee('Deudor Centro')
            ->assertDontSee('Deudor Norte');
    }

    public function test_admin_ve_deudores_de_todas_las_sucursales(): void
    {
        $centro = Sucursal::create(['name' => 'Centro', 'razon_social' => 'Mi Empresa', 'punto_venta' => 90]);
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa', 'punto_venta' => 91]);

        $deudorCentro = Client::create(['name' => 'Deudor Centro', 'email' => 'dc2@test.com']);
        $deudorNorte = Client::create(['name' => 'Deudor Norte', 'email' => 'dn2@test.com']);
        $this->facturaImpaga($deudorCentro, 5000, $centro->id);
        $this->facturaImpaga($deudorNorte, 7000, $norte->id);

        Livewire::actingAs($this->admin())
            ->test('cobranzas.index')
            ->assertSee('Deudor Centro')
            ->assertSee('Deudor Norte');
    }

    public function test_no_puede_cobrar_desde_cobranzas_sin_caja_abierta(): void
    {
        $deudor = Client::create(['name' => 'Deudor', 'email' => 'd@test.com', 'phone' => '3511234567']);
        $this->facturaImpaga($deudor, 5000);

        Livewire::actingAs($this->admin())
            ->test('cobranzas.index')
            ->call('startPayment', $deudor->id, 5000)
            ->set('payAmount', '3000')
            ->call('savePayment')
            ->assertHasErrors('payAmount');

        $this->assertDatabaseCount('client_payments', 0);
    }
}
