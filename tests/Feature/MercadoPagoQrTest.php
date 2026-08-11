<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Services\MercadoPago\MercadoPagoQrService;
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
}
