<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La impresión del ticket térmico la dispara el navegador del cajero, no el
 * servidor (ver TicketPrinterService::renderPng): server-side no hay
 * ninguna ruta de red desde el hosting hacia la impresora del local del
 * cliente, así que WindowsPrintConnector nunca podía funcionar ahí.
 */
class TicketPrintTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    private function invoice(): Invoice
    {
        $client = Client::create(['name' => 'Cliente', 'email' => 'cliente@test.com']);

        return Invoice::create([
            'number' => 'FAC-0001',
            'client_id' => $client->id,
            'sucursal_id' => Sucursal::sole()->id,
            'issue_date' => now(),
            'due_date' => now()->addDays(15),
            'status' => 'draft',
        ]);
    }

    public function test_la_imagen_del_ticket_es_un_png_valido(): void
    {
        $invoice = $this->invoice();

        $response = $this->actingAs($this->admin())->get(route('invoices.ticket-image', $invoice));

        $response->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertNotFalse(getimagesizefromstring($response->getContent()));
    }

    /**
     * El agente local (ver pos-print-agent/) no tiene sesión de navegador,
     * así que esta ruta no usa 'auth' sino 'signed': la URL firmada es la
     * que autoriza el acceso (ver TicketEscPosController).
     */
    public function test_el_escpos_del_ticket_es_una_imagen_rasterizada_valida(): void
    {
        $invoice = $this->invoice();
        $url = URL::temporarySignedRoute('invoices.ticket-escpos', now()->addMinutes(2), ['invoice' => $invoice]);

        $response = $this->get($url);

        $response->assertOk();
        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));

        $bytes = $response->getContent();

        // ESC @ (init) + GS v 0 (imagen rasterizada) + m=0.
        $this->assertStringStartsWith("\x1b\x40\x1d\x76\x30\x00", $bytes);

        // Termina con el corte de papel (GS V 0x42 0x00).
        $this->assertStringEndsWith("\x1d\x56\x42\x00", $bytes);

        // xL/xH del ancho: 384px de ancho de ticket = 48 bytes exactos.
        $widthBytes = ord($bytes[6]) | (ord($bytes[7]) << 8);
        $this->assertSame(48, $widthBytes);
    }

    public function test_no_se_puede_pedir_el_escpos_sin_firma_valida(): void
    {
        $invoice = $this->invoice();

        // route() sin firmar: sin 'signature'/'expires' en la query.
        $this->get(route('invoices.ticket-escpos', $invoice))->assertForbidden();

        $vencida = URL::temporarySignedRoute('invoices.ticket-escpos', now()->subMinute(), ['invoice' => $invoice]);
        $this->get($vencida)->assertForbidden();
    }

    public function test_la_pagina_de_impresion_muestra_la_imagen_del_ticket(): void
    {
        $invoice = $this->invoice();

        $response = $this->actingAs($this->admin())->get(route('invoices.ticket-print', $invoice));

        $response->assertOk();
        $response->assertSee(route('invoices.ticket-image', $invoice), false);
    }

    public function test_vender_con_imprimir_ticket_activado_abre_la_ventana_de_impresion(): void
    {
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'sucursal_id' => Sucursal::sole()->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);
        $product = Product::create(['name' => 'Fideos', 'price' => 1000, 'iva_rate' => 0, 'stock' => 10]);

        $pos = Livewire::actingAs($admin)
            ->test('pos.index')
            ->call('addProduct', $product->id)
            ->call('addPayment')
            ->set('payments.0.method', 'efectivo')
            ->set('printOnSale', true)
            ->call('cobrar');

        $invoice = Invoice::sole();
        $xjs = $pos->effects['xjs'] ?? [];

        $this->assertNotEmpty($xjs, 'Se esperaba que la venta dispare printTicket() para imprimir el ticket.');
        $this->assertStringContainsString('printTicket', $xjs[0]['expression']);
        $this->assertStringContainsString(json_encode(route('invoices.ticket-print', $invoice)), $xjs[0]['expression']);
        $this->assertStringContainsString('signature=', $xjs[0]['expression'], 'La URL del ticket ESC/POS tiene que venir firmada, el agente local no tiene sesión.');
    }

    public function test_vender_con_imprimir_ticket_desactivado_no_abre_ninguna_ventana(): void
    {
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'sucursal_id' => Sucursal::sole()->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);
        $product = Product::create(['name' => 'Fideos', 'price' => 1000, 'iva_rate' => 0, 'stock' => 10]);

        $pos = Livewire::actingAs($admin)
            ->test('pos.index')
            ->call('addProduct', $product->id)
            ->call('addPayment')
            ->set('payments.0.method', 'efectivo')
            ->set('printOnSale', false)
            ->call('cobrar');

        $this->assertEmpty($pos->effects['xjs'] ?? []);
    }

    public function test_ticket_de_cambio_agrega_una_seccion_extra_y_queda_mas_alto(): void
    {
        $invoice = $this->invoice();
        $invoice->items()->create(['description' => 'Fideos', 'quantity' => 1, 'unit_price' => 1000, 'iva_rate' => '21']);

        $normal = $this->actingAs($this->admin())->get(route('invoices.ticket-image', $invoice));
        $conCambio = $this->actingAs($this->admin())->get(route('invoices.ticket-image', ['invoice' => $invoice, 'cambio' => 1]));

        $normal->assertOk();
        $conCambio->assertOk();

        [, $alturaNormal] = getimagesizefromstring($normal->getContent());
        [, $alturaConCambio] = getimagesizefromstring($conCambio->getContent());

        $this->assertGreaterThan($alturaNormal, $alturaConCambio);
    }

    public function test_vender_con_ticket_de_cambio_propaga_el_parametro_a_las_urls_de_impresion(): void
    {
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'sucursal_id' => Sucursal::sole()->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);
        $product = Product::create(['name' => 'Fideos', 'price' => 1000, 'iva_rate' => 0, 'stock' => 10]);

        $pos = Livewire::actingAs($admin)
            ->test('pos.index')
            ->call('addProduct', $product->id)
            ->call('addPayment')
            ->set('payments.0.method', 'efectivo')
            ->set('printOnSale', true)
            ->set('printExchangeSlip', true)
            ->call('cobrar');

        $invoice = Invoice::sole();
        $xjs = $pos->effects['xjs'] ?? [];

        $this->assertNotEmpty($xjs);
        $this->assertStringContainsString(
            json_encode(route('invoices.ticket-print', ['invoice' => $invoice, 'cambio' => 1])),
            $xjs[0]['expression']
        );
    }

    public function test_vender_sin_ticket_de_cambio_no_agrega_el_parametro(): void
    {
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'sucursal_id' => Sucursal::sole()->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);
        $product = Product::create(['name' => 'Fideos', 'price' => 1000, 'iva_rate' => 0, 'stock' => 10]);

        $pos = Livewire::actingAs($admin)
            ->test('pos.index')
            ->call('addProduct', $product->id)
            ->call('addPayment')
            ->set('payments.0.method', 'efectivo')
            ->set('printOnSale', true)
            ->call('cobrar');

        $invoice = Invoice::sole();
        $xjs = $pos->effects['xjs'] ?? [];

        $this->assertNotEmpty($xjs);
        $this->assertStringContainsString(json_encode(route('invoices.ticket-print', $invoice)), $xjs[0]['expression']);
        $this->assertStringNotContainsString('cambio', $xjs[0]['expression']);
    }

    public function test_crear_factura_con_imprimir_al_guardar_activado_abre_la_ventana_de_impresion(): void
    {
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 10]);
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'sucursal_id' => Sucursal::sole()->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);

        $component = Livewire::actingAs($admin)
            ->test('invoices.create')
            ->set('client_id', (string) $client->id)
            ->call('addProductItem', $product->id)
            ->set('tax_rate', '21')
            ->call('addPayment')
            ->set('payments.0.method', 'efectivo')
            ->set('payments.0.amount', '1210')
            ->set('printOnSave', true)
            ->call('save');

        $invoice = Invoice::sole();
        $xjs = $component->effects['xjs'] ?? [];

        $this->assertNotEmpty($xjs, 'Se esperaba que guardar la factura dispare printTicket() para imprimir el ticket.');
        $this->assertStringContainsString('printTicket', $xjs[0]['expression']);
        $this->assertStringContainsString(json_encode(route('invoices.ticket-print', $invoice)), $xjs[0]['expression']);
        $this->assertStringContainsString('signature=', $xjs[0]['expression'], 'La URL del ticket ESC/POS tiene que venir firmada, el agente local no tiene sesión.');
    }
}
