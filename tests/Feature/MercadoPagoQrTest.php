<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\MercadoPago\MercadoPagoQrService;
use App\Support\MercadoPagoPaymentApplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MercadoPagoQrTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    private function invoice(): Invoice
    {
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        $invoice = Invoice::create([
            'number' => 'FAC-0001', 'client_id' => $client->id,
            'issue_date' => now(), 'due_date' => now()->addDays(15), 'status' => 'pending',
        ]);
        $invoice->items()->create(['description' => 'Servicio', 'quantity' => 1, 'unit_price' => 1000]);

        return $invoice;
    }

    public function test_la_pantalla_de_factura_renderiza(): void
    {
        // Sin token configurado: no debe romper y el botón de QR no aparece.
        $fake = $this->createMock(MercadoPagoQrService::class);
        $fake->method('isConfigured')->willReturn(false);
        $this->app->instance(MercadoPagoQrService::class, $fake);

        Livewire::actingAs($this->admin())
            ->test('invoices.show', ['invoice' => $this->invoice()])
            ->assertOk()
            ->assertDontSee('Cobrar con QR');
    }

    public function test_muestra_el_boton_cuando_mp_esta_configurado(): void
    {
        $fake = $this->createMock(MercadoPagoQrService::class);
        $fake->method('isConfigured')->willReturn(true);
        $this->app->instance(MercadoPagoQrService::class, $fake);

        Livewire::actingAs($this->admin())
            ->test('invoices.show', ['invoice' => $this->invoice()])
            ->assertSee('Cobrar con QR');
    }

    public function test_cobro_con_qr_marca_la_factura_pagada_y_registra_en_caja(): void
    {
        $fake = $this->createMock(MercadoPagoQrService::class);
        $fake->method('isConfigured')->willReturn(true);
        $fake->method('createOrder')->willReturn('INV-1-123');
        $fake->method('ensureStoreAndPos')->willReturn([
            'store_id' => 1, 'pos_id' => 2,
            'qr_image' => 'https://example.test/qr.png', 'qr_template' => null,
        ]);
        // Primero pendiente, luego pagado.
        $fake->method('paymentStatus')->willReturnOnConsecutiveCalls('pending', 'paid');
        $this->app->instance(MercadoPagoQrService::class, $fake);

        $invoice = $this->invoice();

        $component = Livewire::actingAs($this->admin())
            ->test('invoices.show', ['invoice' => $invoice])
            ->call('startQrCharge')
            ->assertSet('showQrModal', true)
            ->assertSet('qrState', 'waiting')
            ->assertSet('qrImage', 'https://example.test/qr.png');

        // Primer poll: sigue esperando.
        $component->call('pollQr')->assertSet('qrState', 'waiting');

        // Segundo poll: pagado.
        $component->call('pollQr')->assertSet('qrState', 'paid');

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status->value);
        $this->assertSame('INV-1-123', $invoice->mp_external_reference);

        $payment = $invoice->payments()->where('method', PaymentMethod::MercadoPago->value)->first();
        $this->assertNotNull($payment);
        $this->assertEqualsWithDelta((float) $invoice->total, (float) $payment->amount, 0.01);
    }

    public function test_no_duplica_el_pago_si_se_llama_dos_veces(): void
    {
        $fake = $this->createMock(MercadoPagoQrService::class);
        $fake->method('isConfigured')->willReturn(true);
        $fake->method('createOrder')->willReturn('INV-1-123');
        $fake->method('ensureStoreAndPos')->willReturn([
            'store_id' => 1, 'pos_id' => 2, 'qr_image' => null, 'qr_template' => null,
        ]);
        $fake->method('paymentStatus')->willReturn('paid');
        $this->app->instance(MercadoPagoQrService::class, $fake);

        $invoice = $this->invoice();

        $component = Livewire::actingAs($this->admin())
            ->test('invoices.show', ['invoice' => $invoice])
            ->call('startQrCharge')
            ->call('pollQr')
            ->call('pollQr');

        $this->assertSame(1, $invoice->payments()->where('method', PaymentMethod::MercadoPago->value)->count());
    }

    public function test_si_el_webhook_confirma_el_pago_sin_usuario_logueado_el_polling_completa_el_movimiento_de_caja(): void
    {
        // El webhook de MP (POST público, sin sesión de navegador) corre sin
        // ningún usuario autenticado — antes, si ganaba la carrera contra el
        // polling y creaba el InvoicePayment primero, el cobro quedaba
        // invisible para el arqueo PARA SIEMPRE (el guard de "ya existe el
        // pago" también saltaba el intento de linkear caja en la llamada
        // siguiente). Acá se simula exactamente esa carrera: apply() se
        // llama primero SIN actingAs (como el webhook), y recién después con
        // un cajero logueado con caja abierta (como el polling).
        $invoice = $this->invoice();

        MercadoPagoPaymentApplier::apply($invoice);

        $this->assertSame('paid', $invoice->fresh()->status->value);
        $this->assertSame(1, $invoice->payments()->where('method', PaymentMethod::MercadoPago->value)->count());
        $this->assertSame(0, CashMovement::count()); // sin usuario logueado, no hay caja que linkear

        $cajero = $this->admin();
        CashSession::create(['user_id' => $cajero->id, 'sucursal_id' => Sucursal::sole()->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);
        $this->actingAs($cajero);

        MercadoPagoPaymentApplier::apply($invoice);

        // No duplica el InvoicePayment, pero ahora sí queda el movimiento de caja.
        $this->assertSame(1, $invoice->payments()->where('method', PaymentMethod::MercadoPago->value)->count());
        $movimiento = CashMovement::first();
        $this->assertNotNull($movimiento);
        $this->assertSame('ingreso', $movimiento->type->value);
        $this->assertEqualsWithDelta((float) $invoice->total, (float) $movimiento->amount, 0.01);
    }

    public function test_llamar_apply_dos_veces_ya_autenticado_no_duplica_el_movimiento_de_caja(): void
    {
        $invoice = $this->invoice();
        $cajero = $this->admin();
        CashSession::create(['user_id' => $cajero->id, 'sucursal_id' => Sucursal::sole()->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);
        $this->actingAs($cajero);

        MercadoPagoPaymentApplier::apply($invoice);
        MercadoPagoPaymentApplier::apply($invoice);

        $this->assertSame(1, CashMovement::count());
    }
}
