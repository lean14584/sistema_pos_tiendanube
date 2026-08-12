<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TipoComprobanteInterno;
use App\Mail\InvoiceMail;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceEmailTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    private function factura(array $clientAttrs): Invoice
    {
        $client = Client::create(array_merge(['name' => 'Cliente'], $clientAttrs));

        $invoice = Invoice::create([
            'number' => 'FAC-0001', 'client_id' => $client->id,
            'tipo_comprobante_interno' => TipoComprobanteInterno::FacturaB,
            'issue_date' => now(), 'due_date' => now(), 'tax_rate' => 0, 'status' => 'pending',
        ]);
        $invoice->items()->create(['description' => 'Item', 'quantity' => 1, 'unit_price' => 100, 'iva_rate' => 21]);

        return $invoice->fresh();
    }

    public function test_envia_la_factura_por_email_al_cliente(): void
    {
        Mail::fake();
        $invoice = $this->factura(['email' => 'cliente@test.com']);

        Livewire::actingAs($this->admin())
            ->test('invoices.show', ['invoice' => $invoice])
            ->call('enviarPorEmail');

        Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $mail) =>
            $mail->hasTo('cliente@test.com') && $mail->invoice->is($invoice)
        );
    }

    public function test_no_envia_si_el_cliente_no_tiene_email(): void
    {
        Mail::fake();
        $invoice = $this->factura(['email' => '']);

        Livewire::actingAs($this->admin())
            ->test('invoices.show', ['invoice' => $invoice])
            ->call('enviarPorEmail');

        Mail::assertNothingSent();
    }

    public function test_muestra_link_de_whatsapp_si_el_cliente_tiene_telefono(): void
    {
        $invoice = $this->factura(['email' => 'c@test.com', 'phone' => '5491122334455']);

        Livewire::actingAs($this->admin())
            ->test('invoices.show', ['invoice' => $invoice])
            ->assertSee('wa.me/5491122334455');
    }
}
