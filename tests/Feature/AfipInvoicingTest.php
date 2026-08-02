<?php

namespace Tests\Feature;

use App\Enums\CondicionIva;
use App\Enums\Role;
use App\Enums\TipoComprobante;
use App\Enums\TipoComprobanteInterno;
use App\Enums\TipoDocumento;
use App\Exceptions\Afip\AfipRejectedException;
use App\Exceptions\Afip\AfipValidationException;
use App\Models\Client;
use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Afip\AfipGatewayInterface;
use App\Services\Afip\InvoiceCaeEmitter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Fakes\FakeAfipGateway;
use Tests\TestCase;

class AfipInvoicingTest extends TestCase
{
    use RefreshDatabase;

    private function fake(): FakeAfipGateway
    {
        $fake = new FakeAfipGateway();
        $this->app->instance(AfipGatewayInterface::class, $fake);

        return $fake;
    }

    private function draftInvoice(
        CondicionIva $clienteCondicion,
        TipoDocumento $tipoDocumento = TipoDocumento::Cuit,
        TipoComprobanteInterno $tipoComprobanteInterno = TipoComprobanteInterno::FacturaB,
    ): Invoice {
        $client = Client::create([
            'name' => 'Cliente Test',
            'email' => 'cliente@test.com',
            'tax_id' => '20111111112',
            'condicion_iva' => $clienteCondicion->value,
            'tipo_documento' => $tipoDocumento->value,
        ]);

        $invoice = Invoice::create([
            'number' => 'FAC-0001',
            'client_id' => $client->id,
            'tipo_comprobante_interno' => $tipoComprobanteInterno,
            'issue_date' => now(),
            'due_date' => now()->addDays(15),
            'tax_rate' => 21,
            'status' => 'draft',
        ]);
        $invoice->items()->create(['description' => 'Producto', 'quantity' => 1, 'unit_price' => 1000]);

        return $invoice->fresh();
    }

    public function test_emite_factura_a_cuando_el_switch_fuerza_factura_a(): void
    {
        $this->fake();
        CompanySettings::current()->update(['condicion_iva' => 'responsable_inscripto', 'punto_venta' => 1]);
        $invoice = $this->draftInvoice(CondicionIva::ResponsableInscripto, tipoComprobanteInterno: TipoComprobanteInterno::FacturaA);

        $emitted = app(InvoiceCaeEmitter::class)->emit($invoice);

        $this->assertTrue($emitted->isFiscal);
        $this->assertSame(TipoComprobante::FacturaA, $emitted->tipo_comprobante);
        $this->assertSame(1, $emitted->condicion_iva_receptor_id);
        $this->assertSame(1, $emitted->numero_comprobante_afip);
        $this->assertNotNull($emitted->cae);
        $this->assertNotNull($emitted->emitted_at);
    }

    public function test_emite_factura_b_cuando_el_switch_fuerza_factura_b(): void
    {
        $this->fake();
        CompanySettings::current()->update(['condicion_iva' => 'responsable_inscripto', 'punto_venta' => 1]);
        $invoice = $this->draftInvoice(CondicionIva::ConsumidorFinal, TipoDocumento::SinIdentificar, TipoComprobanteInterno::FacturaB);

        $emitted = app(InvoiceCaeEmitter::class)->emit($invoice);

        $this->assertSame(TipoComprobante::FacturaB, $emitted->tipo_comprobante);
        $this->assertSame(5, $emitted->condicion_iva_receptor_id);
    }

    public function test_una_empresa_monotributista_no_puede_forzar_factura_a_ni_b(): void
    {
        $this->fake();
        CompanySettings::current()->update(['condicion_iva' => 'monotributista', 'punto_venta' => 1]);
        $invoice = $this->draftInvoice(CondicionIva::ResponsableInscripto, tipoComprobanteInterno: TipoComprobanteInterno::FacturaB);

        $this->expectException(AfipValidationException::class);
        app(InvoiceCaeEmitter::class)->emit($invoice);
    }

    public function test_factura_a_requiere_que_el_cliente_tenga_cuit(): void
    {
        $this->fake();
        CompanySettings::current()->update(['condicion_iva' => 'responsable_inscripto', 'punto_venta' => 1]);
        $invoice = $this->draftInvoice(CondicionIva::ResponsableInscripto, TipoDocumento::Dni, TipoComprobanteInterno::FacturaA);

        $this->expectException(AfipValidationException::class);
        app(InvoiceCaeEmitter::class)->emit($invoice);
    }

    public function test_remito_x_y_devolucion_no_se_pueden_emitir_a_afip(): void
    {
        $this->fake();
        CompanySettings::current()->update(['condicion_iva' => 'responsable_inscripto', 'punto_venta' => 1]);
        $invoice = $this->draftInvoice(CondicionIva::ResponsableInscripto, tipoComprobanteInterno: TipoComprobanteInterno::RemitoX);

        $this->expectException(AfipValidationException::class);
        app(InvoiceCaeEmitter::class)->emit($invoice);
    }

    public function test_rechazo_de_afip_no_deja_la_factura_a_medio_emitir(): void
    {
        $fake = $this->fake();
        $fake->rejectNextRequest = true;
        CompanySettings::current()->update(['condicion_iva' => 'responsable_inscripto', 'punto_venta' => 1]);
        $invoice = $this->draftInvoice(CondicionIva::ResponsableInscripto, tipoComprobanteInterno: TipoComprobanteInterno::FacturaA);

        try {
            app(InvoiceCaeEmitter::class)->emit($invoice);
            $this->fail('Se esperaba AfipRejectedException.');
        } catch (AfipRejectedException $e) {
            // esperado
        }

        $invoice->refresh();
        $this->assertFalse($invoice->isFiscal);
        $this->assertNull($invoice->cae);
        $this->assertSame('draft', $invoice->status->value);
    }

    public function test_no_se_puede_reemitir_una_factura_que_ya_tiene_cae(): void
    {
        $this->fake();
        CompanySettings::current()->update(['condicion_iva' => 'responsable_inscripto', 'punto_venta' => 1]);
        $invoice = $this->draftInvoice(CondicionIva::ResponsableInscripto);

        $emitted = app(InvoiceCaeEmitter::class)->emit($invoice);

        $this->expectException(\RuntimeException::class);
        app(InvoiceCaeEmitter::class)->emit($emitted);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_emitir_afip_desde_show_asigna_cae_a_la_factura(): void
    {
        $this->fake();
        CompanySettings::current()->update(['condicion_iva' => 'responsable_inscripto', 'punto_venta' => 1]);
        $invoice = $this->draftInvoice(CondicionIva::ResponsableInscripto);

        Livewire::actingAs($this->admin())
            ->test('invoices.show', ['invoice' => $invoice])
            ->call('emitirAfip')
            ->assertSet('afipError', null);

        $this->assertNotNull($invoice->fresh()->cae);
    }

    public function test_emitir_afip_desde_show_muestra_el_error_de_rechazo(): void
    {
        $fake = $this->fake();
        $fake->rejectNextRequest = true;
        CompanySettings::current()->update(['condicion_iva' => 'responsable_inscripto', 'punto_venta' => 1]);
        $invoice = $this->draftInvoice(CondicionIva::ResponsableInscripto);

        Livewire::actingAs($this->admin())
            ->test('invoices.show', ['invoice' => $invoice])
            ->call('emitirAfip')
            ->assertSet('afipError', $fake->rejectionMessage);

        $this->assertNull($invoice->fresh()->cae);
    }

    public function test_no_se_puede_editar_una_factura_con_cae(): void
    {
        $this->fake();
        CompanySettings::current()->update(['condicion_iva' => 'responsable_inscripto', 'punto_venta' => 1]);
        $invoice = app(InvoiceCaeEmitter::class)->emit($this->draftInvoice(CondicionIva::ResponsableInscripto));

        Livewire::actingAs($this->admin())
            ->test('invoices.edit', ['invoice' => $invoice])
            ->assertForbidden();
    }

    public function test_no_se_puede_borrar_una_factura_con_cae(): void
    {
        $this->fake();
        CompanySettings::current()->update(['condicion_iva' => 'responsable_inscripto', 'punto_venta' => 1]);
        $invoice = app(InvoiceCaeEmitter::class)->emit($this->draftInvoice(CondicionIva::ResponsableInscripto));

        Livewire::actingAs($this->admin())
            ->test('invoices.show', ['invoice' => $invoice])
            ->call('delete')
            ->assertForbidden();

        $this->assertNotNull($invoice->fresh());
    }

    public function test_no_se_puede_volver_a_borrador_una_factura_con_cae(): void
    {
        $this->fake();
        CompanySettings::current()->update(['condicion_iva' => 'responsable_inscripto', 'punto_venta' => 1]);
        $invoice = app(InvoiceCaeEmitter::class)->emit($this->draftInvoice(CondicionIva::ResponsableInscripto));
        $invoice->update(['status' => 'pending']);

        Livewire::actingAs($this->admin())
            ->test('invoices.show', ['invoice' => $invoice])
            ->call('setStatus', 'draft')
            ->assertHasErrors(['status']);

        $this->assertSame('pending', $invoice->fresh()->status->value);
    }

    public function test_pdf_de_una_factura_emitida_incluye_cae_y_qr(): void
    {
        $this->fake();
        CompanySettings::current()->update(['condicion_iva' => 'responsable_inscripto', 'punto_venta' => 1]);
        $invoice = app(InvoiceCaeEmitter::class)->emit($this->draftInvoice(CondicionIva::ResponsableInscripto));

        $response = $this->actingAs($this->admin())->get(route('invoices.pdf', $invoice));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
